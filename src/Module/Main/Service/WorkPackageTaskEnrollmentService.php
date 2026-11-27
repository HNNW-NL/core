<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\Handler\PackageTaskWorkflowHandler;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentResult;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentStatus;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use Symfony\Component\Uid\Ulid;

final readonly class WorkPackageTaskEnrollmentService
{
    public function __construct(
        private PackageTaskRepository $packageTaskRepository,
        private ProjectParticipantRepository $projectParticipantRepository,
        private PackageTaskWorkflowHandler $workflowHandler,
    ) {
    }

    public function enroll(Project $project, Ulid|string $taskId, Profile $profile): WorkPackageTaskEnrollmentResult
    {
        if (!$this->projectParticipantRepository->isActiveParticipant($project, $profile)) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::NotParticipant,
                'Alleen projectdeelnemers kunnen zich inschrijven op taken binnen dit project.',
            );
        }

        $packageTask = $this->packageTaskRepository->findOneForProject($taskId, $project);

        if ($packageTask === null) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::TaskNotFound,
                'De taak is niet gevonden binnen dit project.',
            );
        }

        if ($this->packageTaskRepository->isAssignedToProfile($packageTask, $profile)) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::AlreadyAssignedToCurrentProfile,
                'Je bent al ingeschreven op deze taak.',
                $packageTask,
            );
        }

        if ($packageTask->getAssignedProfile() !== null) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile,
                'Deze taak is al toegewezen aan een andere deelnemer.',
                $packageTask,
            );
        }
        $account = $profile->getAccount();
        if ($account === null) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::NotParticipant,
                'Je profiel is niet gekoppeld aan een account.',
                $packageTask,
            );
        }

        try {
            $this->workflowHandler->assign($packageTask, $profile, $account);
        } catch (\InvalidArgumentException $exception) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::InvalidWorkflowState,
                $exception->getMessage(),
                $packageTask,
            );
        }

        return new WorkPackageTaskEnrollmentResult(
            WorkPackageTaskEnrollmentStatus::Success,
            'Je bent succesvol ingeschreven op deze taak.',
            $packageTask,
        );
    }
}
