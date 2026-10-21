<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Repository\Account\ProfileRepository;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;

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
        $task = $this->packageTaskRepository->find($taskData['id']);
        $assignedProfile = $this->profileRepository->find($taskData['assignedProfile']);

        $workPackage = $this->workPackageRepository->find($taskData['workPackage']);
        $status = $this->statusRepository->find($taskData['status']);

        if (!$task || $task->getWorkpackage()->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid task or project id');
        }

        $profileInProject = array_map(
            fn($participant) => $participant->getProfile()->getId(),
            $this->projectParticipantRepository->findBy(['project' => $project])
        );

        if (!in_array($assignedProfile->getId(), $profileInProject)) {
            throw new \InvalidArgumentException('Invalid Profile id');
        }

        if (!$workPackage->getProject() === $project) {
            throw new \InvalidArgumentException('Invalid work package id');
        }

        if (!$status->getScope() == 'PackageTask') {
            throw new \InvalidArgumentException('Invalid status id');
        }

        $task->setTitle($taskData['title']);
        $task->setDescription($taskData['description']);
        $task->setAssignedProfile($assignedProfile);
        $task->setWorkPackage($workPackage);
        $task->setStatus($status);
        $task->setPriority($taskData['priority']);
        $task->setSlug($taskData['slug']);
        $task->setDueDate(new DateTimeImmutable($taskData['dueDate']));
        $this->em->flush();

    }

    public function deleteTaskById(string $taskId, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskId);

        if (!$task || $task->getWorkpackage()->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid task or project id');
        }

        $task->softDelete();
        $this->em->flush();
    }

    public function restoreTaskById(string $taskId, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskId);

        if (!$task || $task->getWorkpackage()->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid task or project id');
        }

        $task->restore();
        $this->em->flush();
    }

}
