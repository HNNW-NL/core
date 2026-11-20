<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Repository\Account\ProfileRepository;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use DateTimeImmutable;
use InvalidArgumentException;

class ModifyTasksHandler
{
    public function __construct(
        readonly private PackageTaskRepository $packageTaskRepository,
        readonly private ProjectParticipantRepository $projectParticipantRepository,
        readonly private WorkPackageRepository $workPackageRepository,
        readonly private ProfileRepository $profileRepository,
        readonly private StatusRepository $statusRepository,
        readonly private EntityManagerInterface $em
    ) {}

    public function handleModifyTask(array $taskData, Project $project): void
    {
        $this->em->getConnection()->transactional(function () use ($taskData, $project): void {
            $this->updateTask($taskData, $project);
        });
    }

    private function updateTask(array $taskData, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskData['id']);
        $workPackage = $this->workPackageRepository->find($taskData['workPackage']);
        $status = $this->statusRepository->find($taskData['status']);

        if (!$task || $task->getWorkPackage()->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid task or project id');
        }

        if (!$workPackage || $workPackage->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid work package id');
        }
        // Synchronize reassignment with work package deletion.
        $this->em->refresh($workPackage, LockMode::PESSIMISTIC_WRITE);
        if ($workPackage->getDeletedAt() !== null) {
            throw new InvalidArgumentException('Work package has been deleted');
        }

        if (!$status || $status->getScope() !== 'PackageTask') {
            throw new InvalidArgumentException('Invalid status id');
        }

        $assignedProfile = null;

        if (!empty($taskData['assignedProfile'])) {
            $assignedProfile = $this->profileRepository->find($taskData['assignedProfile']);

            if (!$assignedProfile) {
                throw new InvalidArgumentException('Invalid Profile id');
            }

            $participant = $this->projectParticipantRepository->findOneBy(['project' => $project, 'profile' => $assignedProfile]);

            if (!$participant) {
                throw new InvalidArgumentException('Profile not in project');
            }
        }

        $task->setTitle($taskData['title']);
        $task->setDescription($taskData['description']);
        $task->setAssignedProfile($assignedProfile);
        $task->setWorkPackage($workPackage);
        $task->setStatus($status);
        $task->setPriority($taskData['priority']);
        $task->setSlug($taskData['slug']);
        $task->setDueDate($taskData['dueDate'] !== '' ? new DateTimeImmutable($taskData['dueDate']) : null);
        $this->em->flush();

    }

    public function deleteTaskById(string $taskId, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskId);

        if (!$task || $task->getWorkPackage()->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid task or project id');
        }

        $task->softDelete();
        $this->em->flush();
    }

    public function restoreTaskById(string $taskId, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskId);

        if (!$task || $task->getWorkPackage()->getProject() !== $project) {
            throw new InvalidArgumentException('Invalid task or project id');
        }

        $task->restore();
        $this->em->flush();
    }

}
