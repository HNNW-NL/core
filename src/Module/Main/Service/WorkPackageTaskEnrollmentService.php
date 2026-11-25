<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentResult;
use App\Module\Main\DTO\WorkPackageTaskEnrollmentStatus;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function enroll(Project $project, Ulid|string $taskId, Profile $profile): WorkPackageTaskEnrollmentResult
    {
        return $this->entityManager->getConnection()->transactional(
            function () use ($project, $taskId, $profile): WorkPackageTaskEnrollmentResult {
                if (!$this->projectParticipantRepository->isActiveParticipant($project, $profile)) {
                    return new WorkPackageTaskEnrollmentResult(
                        WorkPackageTaskEnrollmentStatus::NotParticipant,
                        'Alleen actieve projectdeelnemers kunnen taken claimen.',
                    );
                }

                $packageTask = $this->packageTaskRepository->findOneForProject(
                    $taskId,
                    $project,
                    true,
                );

                if ($packageTask === null) {
                    return new WorkPackageTaskEnrollmentResult(
                        WorkPackageTaskEnrollmentStatus::TaskNotFound,
                        'De taak bestaat niet binnen dit project of werkpakket.',
                    );
                }

                if ($this->packageTaskRepository->isAssignedToProfile($packageTask, $profile)) {
                    return new WorkPackageTaskEnrollmentResult(
                        WorkPackageTaskEnrollmentStatus::AlreadyAssignedToCurrentProfile,
                        'Deze taak is al aan jou toegewezen.',
                        $packageTask,
                    );
                }

                if ($packageTask->getAssignedProfile() !== null) {
                    return new WorkPackageTaskEnrollmentResult(
                        WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile,
                        'Deze taak is zojuist geclaimd door een andere deelnemer.',
                        $packageTask,
                    );
                }

                $currentStatus = $packageTask->getStatus();
                $currentStatusName = strtolower((string) $currentStatus?->getName());

                if (!in_array($currentStatusName, ['open', 'beschikbaar', 'active', 'actief', ''], true)) {
                    return new WorkPackageTaskEnrollmentResult(
                        WorkPackageTaskEnrollmentStatus::AlreadyAssignedToOtherProfile,
                        'Deze taak kan niet worden geclaimd omdat de status niet open is.',
                        $packageTask,
                    );
                }

                $assignedStatus = $this->statusRepository->findFirstByScopeAndNames(
                    self::ASSIGNED_STATUS_SCOPE,
                    self::ASSIGNED_STATUS_NAMES,
                );

                $packageTask->setAssignedProfile($profile);

                if ($assignedStatus !== null) {
                    $packageTask->setStatus($assignedStatus);
                }

                $this->packageTaskRepository->save($packageTask);

                return new WorkPackageTaskEnrollmentResult(
                    WorkPackageTaskEnrollmentStatus::Success,
                    'Je hebt de taak succesvol geclaimd.',
                    $packageTask,
                );
            },
        );
    }
}
