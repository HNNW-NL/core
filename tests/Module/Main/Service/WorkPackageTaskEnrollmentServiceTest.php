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
use PHPUnit\Framework\TestCase;

final class WorkPackageTaskEnrollmentServiceTest extends TestCase
{
    public function testRejectsProfileThatIsNotAnActiveParticipant(): void
    {
        $project = new Project();
        $profile = new Profile();
        $participantRepository = $this->createMock(ProjectParticipantRepository::class);
        $participantRepository->expects(self::once())
            ->method('isActiveParticipant')
            ->with($project, $profile)
            ->willReturn(false);
        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->expects(self::never())->method('findOneForProject');
        $taskRepository->expects(self::never())->method('save');

        $result = $this->createService($taskRepository, $participantRepository)->enroll(
            $project,
            (new PackageTask())->getId(),
            $profile,
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::NotParticipant, $result->status);
        self::assertFalse($result->isSuccessful());
    }

    public function testReturnsTaskNotFoundWhenTaskDoesNotBelongToProject(): void
    {
        $project = new Project();
        $profile = new Profile();
        $taskId = (new PackageTask())->getId();
        $participantRepository = $this->activeParticipantRepository($project, $profile);
        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->expects(self::once())
            ->method('findOneForProject')
            ->with($taskId, $project)
            ->willReturn(null);
        $taskRepository->expects(self::never())->method('save');

        $result = $this->createService($taskRepository, $participantRepository)->enroll(
            $project,
            $taskId,
            $profile,
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::TaskNotFound, $result->status);
        self::assertFalse($result->isSuccessful());
    }

    public function testReportsTaskAlreadyAssignedToCurrentProfile(): void
    {
        $project = new Project();
        $profile = new Profile();
        $task = new PackageTask();
        $participantRepository = $this->activeParticipantRepository($project, $profile);
        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->method('findOneForProject')->willReturn($task);
        $taskRepository->expects(self::once())
            ->method('isAssignedToProfile')
            ->with($task, $profile)
            ->willReturn(true);
        $taskRepository->expects(self::never())->method('save');

        $result = $this->createService($taskRepository, $participantRepository)->enroll(
            $project,
            $task->getId(),
            $profile,
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::AlreadyAssignedToCurrentProfile, $result->status);
        self::assertTrue($result->isSuccessful());
        self::assertSame($task, $result->packageTask);
    }

    public function testReportsTaskAlreadyAssignedToAnotherProfile(): void
    {
        $project = new Project();
        $profile = new Profile();
        $task = (new PackageTask())->setAssignedProfile(new Profile());
        $participantRepository = $this->activeParticipantRepository($project, $profile);
        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->method('findOneForProject')->willReturn($task);
        $taskRepository->expects(self::once())
            ->method('isAssignedToProfile')
            ->with($task, $profile)
            ->willReturn(false);
        $taskRepository->expects(self::never())->method('save');

        $result = $this->createService($taskRepository, $participantRepository)->enroll(
            $project,
            $task->getId(),
            $profile,
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile, $result->status);
        self::assertFalse($result->isSuccessful());
        self::assertSame($task, $result->packageTask);
    }

    public function testAssignsTaskToActiveParticipantAndSavesIt(): void
    {
        $project = new Project();
        $profile = new Profile();
        $task = new PackageTask();
        $assignedStatus = (new Status())->setName('assigned')->setScope('package_task');
        $participantRepository = $this->activeParticipantRepository($project, $profile);
        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->method('findOneForProject')->willReturn($task);
        $taskRepository->method('isAssignedToProfile')->willReturn(false);
        $taskRepository->expects(self::once())->method('save')->with($task);
        $statusRepository = $this->createMock(StatusRepository::class);
        $statusRepository->expects(self::once())
            ->method('findFirstByScopeAndNames')
            ->with('package_task', ['assigned', 'toegewezen', 'in progress', 'in-progress', 'in behandeling', 'bezig'])
            ->willReturn($assignedStatus);

        $result = $this->createService($taskRepository, $participantRepository, $statusRepository)->enroll(
            $project,
            $task->getId(),
            $profile,
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::Success, $result->status);
        self::assertTrue($result->isSuccessful());
        self::assertSame($task, $result->packageTask);
        self::assertSame($profile, $task->getAssignedProfile());
        self::assertSame($assignedStatus, $task->getStatus());
    }

    public function testStillAssignsTaskWhenNoAssignedStatusIsConfigured(): void
    {
        $project = new Project();
        $profile = new Profile();
        $task = new PackageTask();
        $participantRepository = $this->activeParticipantRepository($project, $profile);
        $taskRepository = $this->createMock(PackageTaskRepository::class);
        $taskRepository->method('findOneForProject')->willReturn($task);
        $taskRepository->method('isAssignedToProfile')->willReturn(false);
        $taskRepository->expects(self::once())->method('save')->with($task);
        $statusRepository = $this->createStub(StatusRepository::class);
        $statusRepository->method('findFirstByScopeAndNames')->willReturn(null);

        $result = $this->createService($taskRepository, $participantRepository, $statusRepository)->enroll(
            $project,
            $task->getId(),
            $profile,
        );

        self::assertSame(WorkPackageTaskEnrollmentStatus::Success, $result->status);
        self::assertSame($profile, $task->getAssignedProfile());
        self::assertNull($task->getStatus());
    }

    private function createService(
        PackageTaskRepository $taskRepository,
        ProjectParticipantRepository $participantRepository,
        ?StatusRepository $statusRepository = null,
    ): WorkPackageTaskEnrollmentService {
        return new WorkPackageTaskEnrollmentService(
            $taskRepository,
            $participantRepository,
            $statusRepository ?? $this->createStub(StatusRepository::class),
        );
    }

    private function activeParticipantRepository(Project $project, Profile $profile): ProjectParticipantRepository
    {
        $repository = $this->createMock(ProjectParticipantRepository::class);
        $repository->expects(self::once())
            ->method('isActiveParticipant')
            ->with($project, $profile)
            ->willReturn(true);

        return $repository;
    }
}
