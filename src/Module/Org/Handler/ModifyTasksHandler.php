<?php

namespace App\Module\Org\Handler;

use App\Entity\Account\Account;
use App\Entity\Project\Project;
use App\Module\Main\Handler\PackageTaskWorkflowHandler;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Repository\Account\ProfileRepository;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        readonly private PackageTaskWorkflowHandler $taskWorkflowHandler,
        readonly private EntityManagerInterface $em
    ) {}

    public function handleModifyTask(array $taskData, Project $project, Account $actor): void
    {
        $this->em->getConnection()->transactional(
            fn () => $this->doHandleModifyTask($taskData, $project, $actor),
        );
    }

    private function doHandleModifyTask(array $taskData, Project $project, Account $actor): void
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

        if (!$status || strcasecmp((string) $status->getScope(), 'PackageTask') !== 0) {
            throw new InvalidArgumentException('Invalid status id');
        }
        if ($status !== $task->getStatus()) {
            throw new InvalidArgumentException('Task statuses can only be changed through workflow actions.');
        }

        $assignedProfile = null;

        if (!empty($taskData['assignedProfile'])) {
            $assignedProfile = $this->profileRepository->find($taskData['assignedProfile']);

            if (!$assignedProfile) {
                throw new InvalidArgumentException('Invalid Profile id');
            }

            if (!$this->projectParticipantRepository->isActiveParticipant($project, $assignedProfile)) {
                throw new InvalidArgumentException('Profile not in project');
            }
        }

        $dueDate = $taskData['dueDate'] !== '' ? new DateTimeImmutable($taskData['dueDate']) : null;
        $currentAssignedProfile = $task->getAssignedProfile();
        if (($currentAssignedProfile?->getId()->toBase32()) !== ($assignedProfile?->getId()->toBase32())) {
            if ($assignedProfile === null) {
                throw new InvalidArgumentException('An assigned task cannot be unassigned outside the workflow.');
            }

            $this->taskWorkflowHandler->assign($task, $assignedProfile, $actor, true);
        }

        $task->setTitle($taskData['title']);
        $task->setDescription($taskData['description']);
        $task->setWorkPackage($workPackage);
        $task->setPriority($taskData['priority']);
        $task->setSlug($taskData['slug']);
        $task->setDueDate($dueDate);
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
