<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Module\Main\Service\ProjectApplyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

// deze tests gebruiken de fixtures: TestUser doet al mee aan Test-Project, AdminUser nog niet
class ProjectApplyServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProjectApplyService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(ProjectApplyService::class);
    }

    private function getProject(): Project
    {
        return $this->entityManager->getRepository(Project::class)->findOneBy(['slug' => 'Test-Project']);
    }

    private function getProfile(string $username): Profile
    {
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['username' => $username]);

        return $account->getProfile();
    }

    private function makeDto(string $motivation): ProjectApplyDto
    {
        $dto = new ProjectApplyDto();
        $dto->motivation = $motivation;

        return $dto;
    }

    private function findApplication(Project $project, Profile $profile): ?ProjectApplication
    {
        return $this->entityManager->getRepository(ProjectApplication::class)->findOneBy([
            'project' => $project,
            'profile' => $profile,
        ]);
    }

    // apply

    public function testWhenProfileHasNotAppliedShouldCreateApplication(): void
    {
        $project = $this->getProject();
        $profile = $this->getProfile('AdminUser');

        $result = $this->service->apply($project, $profile, $this->makeDto('  ik wil graag meedoen aan dit project  '));

        $this->assertSame(ProjectApplyStatus::Success, $result->status);

        $this->entityManager->clear();
        $application = $this->findApplication($this->getProject(), $this->getProfile('AdminUser'));

        $this->assertNotNull($application);
        $this->assertSame('ik wil graag meedoen aan dit project', $application->getMotivation());
        $this->assertSame('send', $application->getStatus()->getName());
        $this->assertNull($application->getDeletedAt());
    }

    public function testWhenProfileAlreadyAppliedShouldGiveError(): void
    {
        $project = $this->getProject();
        $profile = $this->getProfile('AdminUser');

        $this->service->apply($project, $profile, $this->makeDto('eerste aanmelding voor dit project'));
        $result = $this->service->apply($project, $profile, $this->makeDto('tweede aanmelding voor dit project'));

        $this->assertSame(ProjectApplyStatus::AlreadyApplied, $result->status);
        $this->assertSame('eerste aanmelding voor dit project', $this->findApplication($project, $profile)->getMotivation());
    }

    public function testWhenProfileIsAlreadyParticipantShouldGiveError(): void
    {
        $result = $this->service->apply($this->getProject(), $this->getProfile('TestUser'), $this->makeDto('ik wil nog een keer meedoen'));

        $this->assertSame(ProjectApplyStatus::AlreadyParticipant, $result->status);
    }

    public function testWhenApplicationWasCancelledShouldRestoreIt(): void
    {
        $project = $this->getProject();
        $profile = $this->getProfile('AdminUser');

        $first = $this->service->apply($project, $profile, $this->makeDto('eerste aanmelding voor dit project'));
        $first->application->markAsReviewed();
        $first->application->setReviewerProfile($this->getProfile('TestUser'));
        $first->application->setReview('afgewezen');
        $first->application->softDelete();
        $this->entityManager->flush();

        $result = $this->service->apply($project, $profile, $this->makeDto('nieuwe aanmelding na annuleren'));

        $this->assertSame(ProjectApplyStatus::Success, $result->status);
        $this->assertEquals($first->application->getId(), $result->application->getId());
        $this->assertNull($result->application->getDeletedAt());
        $this->assertSame('nieuwe aanmelding na annuleren', $result->application->getMotivation());
        $this->assertNull($result->application->getReviewedAt());
        $this->assertNull($result->application->getReviewerProfile());
        $this->assertNull($result->application->getReview());
        $this->assertSame('send', $result->application->getStatus()->getName());
    }

    public function testWhenProjectIsPrivateShouldGiveError(): void
    {
        $project = $this->getProject();
        $project->setVisibility('private');

        $result = $this->service->apply($project, $this->getProfile('AdminUser'), $this->makeDto('ik wil graag meedoen aan dit project'));

        $this->assertSame(ProjectApplyStatus::NotOpen, $result->status);
        $this->assertNull($this->findApplication($project, $this->getProfile('AdminUser')));
    }

    // check

    public function testWhenProfileHasNotAppliedCheckShouldGiveNull(): void
    {
        $this->assertNull($this->service->check($this->getProject(), $this->getProfile('AdminUser')));
    }
}
