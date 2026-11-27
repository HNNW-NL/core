<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Module\Main\Service\ProjectApplicationService;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ProjectApplicationServiceTest extends TestCase
{
    #[TestWith(['unverified'])]
    #[TestWith(['closed'])]
    #[TestWith(['owner'])]
    #[TestWith(['wrong-profile'])]
    #[TestWith(['participant'])]
    public function testInvalidApplicantOrProjectIsNeverPersisted(string $scenario): void
    {
        $account = new Account();
        if ($scenario !== 'unverified') { $account->verifyEmail(); }
        $profile = (new Profile())->setAccount($scenario === 'wrong-profile' ? new Account() : $account);
        $project = (new Project())->setVisibility('public')->setEndDate(null)->setStatus((new Status())->setScope('project')->setName($scenario === 'closed' ? 'closed' : 'active'));
        $project->publish();
        if ($scenario === 'owner') { $project->setOwnerAccount($account); }
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn ($operation) => $operation());
        $repository = $this->createStub(\App\Repository\Project\ProjectParticipantRepository::class);
        $repository->method('isActiveParticipant')->willReturn($scenario === 'participant');
        $em->method('getRepository')->willReturn($repository);
        $em->expects(self::never())->method('persist');
        $service = new ProjectApplicationService($em, $this->createStub(StatusRepository::class), $repository);
        $this->expectException($scenario === 'wrong-profile'
            ? \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class : \DomainException::class);
        $service->submit($project, $account, $profile, str_repeat('a', 20));
    }

    public function testAnotherProfileCannotWithdrawApplication(): void
    {
        $application = (new ProjectApplication())->setProject(new Project())->setProfile(new Profile())
            ->setStatus((new Status())->setScope('ProjectApplication')->setName('send'));
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn ($operation) => $operation());
        $service = new ProjectApplicationService($em, $this->createStub(StatusRepository::class), $this->createStub(\App\Repository\Project\ProjectParticipantRepository::class));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);
        $service->withdraw($application, new Profile());
    }

    #[TestWith([19, false])]
    #[TestWith([20, true])]
    #[TestWith([2000, true])]
    #[TestWith([2001, false])]
    #[TestWith([20, true, 'published'])]
    #[TestWith([20, true, 'active', 'withdrawn'])]
    #[TestWith([20, true, 'published', 'accepted'])]
    #[TestWith([20, true, 'active', 'rejected'])]
    #[TestWith([20, true, 'active', null, 'private'])]
    public function testMotivationAndDuplicateSubmission(int $length, bool $valid, string $projectStatus = 'active', ?string $existingState = null, string $visibility = 'public'): void
    {
        $account = new Account();
        $account->verifyEmail();
        $profile = (new Profile())->setAccount($account);
        $project = (new Project())->setVisibility($visibility)->setStatus((new Status())->setScope('project')->setName($projectStatus))->setEndDate(null);
        $project->publish();
        $existing = null;
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(function (array $criteria) use (&$existing) {
            return $existing;
        });
        $participants = $this->createStub(\App\Repository\Project\ProjectParticipantRepository::class);
        $participants->method('isActiveParticipant')->willReturn(false);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn ($class) => $class === ProjectApplication::class ? $repository : $participants);
        $em->method('wrapInTransaction')->willReturnCallback(static fn ($operation) => $operation());
        $em->expects($valid && $visibility === 'public' ? self::once() : self::never())->method('persist')->willReturnCallback(function ($application) use (&$existing) { $existing = $application; });
        $statuses = $this->createStub(StatusRepository::class);
        $statuses->method('findFirstByScopeAndNames')->willReturn((new Status())->setScope('ProjectApplication')->setName('send'));
        $service = new ProjectApplicationService($em, $statuses, $participants);
        if (!$valid) {
            $this->expectException(\DomainException::class);
        }
        if ($visibility !== 'public') {
            $this->expectException(\DomainException::class);
            $this->expectExceptionMessage('niet open');
        }
        $submission = $service->submitWithResult($project, $account, $profile, str_repeat('é', $length));
        self::assertTrue($submission->created);
        $application = $submission->application;
        self::assertSame(str_repeat('é', $length), $application->getMotivation());
        self::assertSame($profile, $application->getProfile());
        self::assertSame('Ingediend', $service->label($application));
        if ($existingState !== null) {
            if ($existingState === 'withdrawn') {
                $application->softDelete();
            } else {
                $application->setStatus((new Status())->setScope('ProjectApplication')->setName($existingState));
            }
            if ($existingState === 'withdrawn') {
                $restored = $service->submitWithResult($project, $account, $profile, str_repeat('�', $length));
                self::assertSame($application, $restored->application);
                self::assertTrue($restored->created);
                self::assertNull($application->getDeletedAt());
            } else {
                $this->expectException(\DomainException::class);
                $this->expectExceptionMessage('behandeld');
            }
        }
        self::assertSame($application, $service->submit($project, $account, $profile, str_repeat('é', $length)));
        self::assertFalse($service->submitWithResult($project, $account, $profile, str_repeat('é', $length))->created);
    }

    #[TestWith(['send', true])]
    #[TestWith(['accepted', false])]
    #[TestWith(['rejected', false])]
    public function testOnlyPendingApplicationsCanBeWithdrawn(string $status, bool $allowed): void
    {
        $profile = new Profile();
        $application = (new ProjectApplication())->setProject(new Project())->setProfile($profile)
            ->setStatus((new Status())->setScope('ProjectApplication')->setName($status));
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(static fn ($operation) => $operation());
        $service = new ProjectApplicationService($em, $this->createStub(StatusRepository::class), $this->createStub(\App\Repository\Project\ProjectParticipantRepository::class));
        if (!$allowed) {
            $this->expectException(\DomainException::class);
        }
        self::assertTrue($service->withdraw($application, $profile));
        self::assertSame('Ingetrokken', $service->label($application));
        self::assertFalse($service->withdraw($application, $profile));
        self::assertNull($application->getParticipant());
    }
}
