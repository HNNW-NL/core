<?php

namespace App\Tests\Module\Org;

use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ApplicationReviewTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectParticipant', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectApplication', false])]
    public function testInvalidOrDisabledTransitionsCannotBeExecuted(string $scope, bool $enabled): void
    {
        $project = new Project();
        $reviewer = new Profile();
        $status = (new Status())->setScope($scope)->setName('send');
        $status->configureApplicationTransitions($enabled, $enabled);
        $application = (new ProjectApplication())->setProject($project)->setStatus($status);
        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->expects(self::exactly(2))->method('findApplicationForProject')->willReturn($application);
        $participants->expects(self::never())->method('findOneBy');
        $statuses = $this->createMock(StatusRepository::class);
        $statuses->expects(self::never())->method('findFirstByScopeAndNames');
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $operation): array => $operation(),
        );
        $em->expects(self::never())->method('persist');
        $em->expects(self::exactly(2))->method('lock')->with($project, LockMode::PESSIMISTIC_WRITE);
        $workflow = new ProjectParticipantUpdateRoleHandler(
            $participants, $this->createStub(ProjectRoleRepository::class), $em, $statuses,
        );

        self::assertSame(!$enabled, $workflow->isPending($application));
        self::assertSame([], $workflow->configuredTransitions($status));
        self::assertFalse($workflow->acceptApplication($project, (string) $application->getId(), $reviewer)['success']);
        self::assertFalse($workflow->rejectApplication($project, (string) $application->getId(), 'Afgewezen', $reviewer)['success']);
        self::assertSame($status, $application->getStatus());
        self::assertNull($application->getReviewerProfile());
    }

    public function testPendingRequiresApplicationScopeAndKnownStatus(): void
    {
        $workflow = new ProjectParticipantUpdateRoleHandler(
            $this->createStub(ProjectParticipantRepository::class),
            $this->createStub(ProjectRoleRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(StatusRepository::class),
        );
        $application = new ProjectApplication();
        self::assertFalse($workflow->isPending($application));
        foreach (['send', 'sent', 'pending', 'submitted', 'ingediend'] as $name) {
            $application->setStatus((new Status())->setScope('ProjectApplication')->setName($name));
            self::assertTrue($workflow->isPending($application));
        }
        $application->setStatus((new Status())->setScope('ProjectApplication')->setName('accepted'));
        self::assertFalse($workflow->isPending($application));
    }

    public function testDisplayedAcceptanceMatchesApplicationReview(): void
    {
        $project = new Project();
        $applicant = new Profile();
        $reviewer = new Profile();
        $pending = (new Status())->setScope('ProjectApplication')->setName('send');
        $accepted = (new Status())->setScope('ProjectApplication')->setName('accepted');
        $active = (new Status())->setScope('ProjectParticipant')->setName('active');
        $application = (new ProjectApplication())->setProject($project)->setProfile($applicant)->setStatus($pending);
        $id = (string) $application->getId();

        $statuses = $this->createStub(StatusRepository::class);
        $statuses->method('findFirstByScopeAndNames')->willReturnCallback(
            static fn (string $scope, array $names): ?Status => match (true) {
                $scope === 'ProjectApplication' && in_array('accepted', $names, true) => $accepted,
                $scope === 'ProjectParticipant' && in_array('active', $names, true) => $active,
                default => null,
            },
        );
        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->expects(self::exactly(2))->method('findApplicationForProject')
            ->with($id, $project, true)->willReturn($application);
        $participants->expects(self::once())->method('findOneBy')->with(['project' => $project, 'profile' => $applicant])
            ->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::exactly(2))->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $operation): array => $operation(),
        );
        $em->expects(self::exactly(2))->method('lock')->with($project, LockMode::PESSIMISTIC_WRITE);
        $em->expects(self::once())->method('persist')->with(self::callback(
            static fn ($participant): bool => $participant instanceof ProjectParticipant,
        ));
        $workflow = new ProjectParticipantUpdateRoleHandler(
            $participants, $this->createStub(ProjectRoleRepository::class), $em, $statuses,
        );

        self::assertSame(['Geaccepteerd'], $workflow->configuredTransitions($pending));
        self::assertTrue($workflow->acceptApplication($project, $id, $reviewer)['success']);
        self::assertSame($accepted, $application->getStatus());
        self::assertFalse($workflow->acceptApplication($project, $id, $reviewer)['success']);
        self::assertSame([], $workflow->configuredTransitions($accepted));
    }
}
