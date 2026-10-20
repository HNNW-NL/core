<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\Project;
use App\Repository\Project\PackageTaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModifyTasksHandler
{
    public function __construct(
        readonly private PackageTaskRepository $packageTaskRepository,
        readonly private EntityManagerInterface $em
    ) {}

    public function handleModifyTask(array $taskData, Project $project): void
    {
        $task = $this->packageTaskRepository->find($taskData['id']);

        if (!$task || $task->getWorkpackage()->getProject() !== $project) {
            throw new \InvalidArgumentException('Invalid task or project id');
        }
        $task->setTitle($taskData['title']);
        $task->setDescription($taskData['description']);
        $task->setWorkPackage($taskData['workPackage']);
        $task->setStatus($taskData['status']);
        $task->setPriority($taskData['priority']);
        $task->setSlug($taskData['slug']);
        $task->setDueDate($taskData['dueDate']);
        $task->setAssignedProfile($taskData['assignedProfile']);
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
