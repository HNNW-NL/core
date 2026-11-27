<?php

namespace App\Module\Main\Service;

use App\Module\Org\Service\ProjectLifecycle;
use App\Module\Org\Service\ProjectWriteLock;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Repository\Project\PackageTaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class PackageTaskService
{
    public function __construct(
        private readonly ProjectWriteLock $projectWriteLock,
        private readonly PackageTaskRepository $packageTaskRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function selfAssignTask(PackageTask $task, Profile $profile): PackageTask
    {
        if ($task->getAssignedProfile() !== null) {
            return $task;
        }

        $task->setAssignedProfile($profile);

        $this->entityManager->flush();

        return $task;
    }

    public function completeTask(PackageTask $task, Status $completedStatus): PackageTask
    {
        $project = $task->getWorkPackage()?->getProject();
        if ($project === null) {
            throw new \InvalidArgumentException('Task does not belong to a project.');
        }

        return $this->projectWriteLock->run($project, function () use ($task, $completedStatus, $project): PackageTask {
            ProjectLifecycle::assertTaskChangesAllowed($project);
            $this->entityManager->refresh($task);
            if ($task->getWorkPackage()?->getProject() !== $project) {
                throw new \InvalidArgumentException('Task does not belong to this project.');
            }
            $task->setStatus($completedStatus);

            $this->entityManager->flush();

            return $task;
        });
    }
}