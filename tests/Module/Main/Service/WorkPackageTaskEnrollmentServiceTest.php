<?php

namespace App\Tests\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentStatus;
use App\Module\Main\Service\WorkPackageTaskEnrollmentService;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class WorkPackageTaskEnrollmentServiceTest extends TestCase
{
    public function testWhenProfileIsNotActiveParticipantShouldGiveError(): void
    {
        $service = $this->createService(false, null);
        $result = $service->enroll(new Project(), Ulid::generate(), new Profile());

        self::assertSame(WorkPackageTaskEnrollmentStatus::NotParticipant, $result->status);
        self::assertStringContainsString('actieve projectdeelnemers', $result->message);
    }

    public function testWhenPackageTaskDoesntExistShouldGiveError(): void
    {
        $service = $this->createService(true, null);
        $result = $service->enroll(new Project(), Ulid::generate(), new Profile());

        self::assertSame(WorkPackageTaskEnrollmentStatus::TaskNotFound, $result->status);
    }

    public function testWhenTaskIsAlreadyAssignedToAnotherProfileShouldGiveClearError(): void
    {
        $task = new PackageTask();
        $task->setAssignedProfile(new Profile());
        $service = $this->createService(true, $task);

        $result = $service->enroll(new Project(), Ulid::generate(), new Profile());

        self::assertSame(WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile, $result->status);
        self::assertStringContainsString('geclaimd', $result->message);
    }

    public function testWhenProfileIsAlreadyAssignedToTaskShouldGiveClearError(): void
    {
        $profile = new Profile();
        $task = (new PackageTask())->setAssignedProfile($profile);
        $service = $this->createService(true, $task, $profile);

        $result = $service->enroll(new Project(), Ulid::generate(), $profile);

        self::assertSame(WorkPackageTaskEnrollmentStatus::AlreadyAssignedToCurrentProfile, $result->status);
    }

    public function testActiveParticipantCanClaimTaskAndStatusChangesOnServer(): void
    {
        $profile = new Profile();
        $task = new PackageTask();
        $open = (new Status())->setName('Open')->setScope('package_task');
        $assigned = (new Status())->setName('Assigned')->setScope('package_task');
        $task->setStatus($open);

        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->method('isActiveParticipant')->willReturn(true);
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->method('findOneForProject')->willReturn($task);
        $tasks->method('isAssignedToProfile')->willReturnCallback(
            static fn (PackageTask $value, Profile $candidate): bool => $value->getAssignedProfile() === $candidate,
        );
        $tasks->expects(self::once())->method('save')->with($task);
        $statuses = $this->createMock(StatusRepository::class);
        $statuses->method('findFirstByScopeAndNames')->willReturn($assigned);
        $service = new WorkPackageTaskEnrollmentService($tasks, $participants, $statuses, $this->transactionalEntityManager());

        $result = $service->enroll(new Project(), Ulid::generate(), $profile);

        self::assertSame(WorkPackageTaskEnrollmentStatus::Success, $result->status);
        self::assertSame($profile, $task->getAssignedProfile());
        self::assertSame($assigned, $task->getStatus());
    }

    public function testSecondConcurrentClaimGetsAlreadyAssignedMessage(): void
    {
        $firstProfile = new Profile();
        $secondProfile = new Profile();
        $task = new PackageTask();
        $task->setStatus((new Status())->setName('Open')->setScope('package_task'));

        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->method('isActiveParticipant')->willReturn(true);
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->method('findOneForProject')->willReturn($task);
        $tasks->method('isAssignedToProfile')->willReturnCallback(
            static fn (PackageTask $value, Profile $candidate): bool => $value->getAssignedProfile() === $candidate,
        );
        $statuses = $this->createMock(StatusRepository::class);
        $statuses->method('findFirstByScopeAndNames')->willReturn(
            (new Status())->setName('Assigned')->setScope('package_task'),
        );
        $service = new WorkPackageTaskEnrollmentService($tasks, $participants, $statuses, $this->transactionalEntityManager());

        $first = $service->enroll(new Project(), Ulid::generate(), $firstProfile);
        $second = $service->enroll(new Project(), Ulid::generate(), $secondProfile);

        self::assertSame(WorkPackageTaskEnrollmentStatus::Success, $first->status);
        self::assertSame(WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile, $second->status);
        self::assertStringContainsString('geclaimd', $second->message);
    }

    private function createService(bool $participant, ?PackageTask $task, ?Profile $alreadyAssignedProfile = null): WorkPackageTaskEnrollmentService
    {
        $participants = $this->createMock(ProjectParticipantRepository::class);
        $participants->method('isActiveParticipant')->willReturn($participant);
        $tasks = $this->createMock(PackageTaskRepository::class);
        $tasks->method('findOneForProject')->willReturn($task);
        $tasks->method('isAssignedToProfile')->willReturnCallback(
            static fn (PackageTask $value, Profile $profile): bool => $alreadyAssignedProfile !== null
                && $value->getAssignedProfile() === $alreadyAssignedProfile
                && $value->getAssignedProfile() === $profile,
        );
        $statuses = $this->createMock(StatusRepository::class);

        return new WorkPackageTaskEnrollmentService($tasks, $participants, $statuses, $this->transactionalEntityManager());
    }

    private function transactionalEntityManager(): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $operation) => $operation());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
