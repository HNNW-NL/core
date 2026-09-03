<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Repository\Project\PackageTaskRepository;
use Doctrine\ORM\EntityManagerInterface;

class PackageTaskService
{
    public function __construct(
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
        $task->setStatus($completedStatus);

        $this->entityManager->flush();

        return $task;
    }
}