<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentResult;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentStatus;
use App\Module\Org\Service\ProjectLifecycle;
use App\Module\Org\Service\ProjectWriteLock;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use Symfony\Component\Uid\Ulid;

final readonly class WorkPackageTaskEnrollmentService
{
    private const ASSIGNED_STATUS_SCOPE = 'package_task';

    private const ASSIGNED_STATUS_NAMES = [
        'assigned',
        'toegewezen',
        'in progress',
        'in-progress',
        'in behandeling',
        'bezig',
    ];

    public function __construct(
        private PackageTaskRepository $packageTaskRepository,
        private ProjectParticipantRepository $projectParticipantRepository,
        private StatusRepository $statusRepository,
        private ProjectWriteLock $projectWriteLock,
    ) {
    }

    public function enroll(Project $project, Ulid|string $taskId, Profile $profile): WorkPackageTaskEnrollmentResult
    {
        return $this->projectWriteLock->run($project, fn (): WorkPackageTaskEnrollmentResult => $this->enrollInLockedProject($project, $taskId, $profile));
    }

    private function enrollInLockedProject(Project $project, Ulid|string $taskId, Profile $profile): WorkPackageTaskEnrollmentResult
    {
        if (!ProjectLifecycle::allowsTaskChanges($project)) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::ProjectClosed,
                'Dit project staat geen taakinschrijvingen meer toe.',
            );
        }
        if (!$this->projectParticipantRepository->isActiveParticipant($project, $profile)) {
            return new WorkPackageTaskEnrollmentResult(
                WorkPackageTaskEnrollmentStatus::NotParticipant,
                'Alleen projectdeelnemers kunnen zich inschrijven op taken binnen dit project.',
            );
        }

        $packageTask = $this->packageTaskRepository->findOneForProject($taskId, $project, true);

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
        $packageTask->setAssignedProfile($profile);

        $assignedStatus = $this->statusRepository->findFirstByScopeAndNames(
            self::ASSIGNED_STATUS_SCOPE,
            self::ASSIGNED_STATUS_NAMES,
        );

        if ($assignedStatus !== null) {
            $packageTask->setStatus($assignedStatus);
        }

        $this->packageTaskRepository->save($packageTask);

        return new WorkPackageTaskEnrollmentResult(
            WorkPackageTaskEnrollmentStatus::Success,
            'Je bent succesvol ingeschreven op deze taak.',
            $packageTask,
        );
    }
}
