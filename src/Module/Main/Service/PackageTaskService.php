<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Enum\PackageTaskWorkflowStatus;
use App\Module\Main\Handler\PackageTaskWorkflowHandler;
use App\Security\CurrentAccountProvider;

class PackageTaskService
{
    public function __construct(
        private readonly PackageTaskWorkflowHandler $workflowHandler,
        private readonly CurrentAccountProvider $currentAccountProvider,
    ) {
    }

    public function selfAssignTask(PackageTask $task, Profile $profile): PackageTask
    {
        $account = $this->currentAccountProvider->getAccount();
        if ($account === null) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('A signed-in account is required.');
        }
        if ($account->getProfile()?->getId()->equals($profile->getId()) !== true) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You can only assign tasks to your own profile.');
        }

        $this->workflowHandler->assign($task, $profile, $account);

        return $task;
    }

    public function completeTask(PackageTask $task, Status $completedStatus): PackageTask
    {
        if (PackageTaskWorkflowStatus::fromStoredName($completedStatus->getName()) !== PackageTaskWorkflowStatus::ACCEPTED
            || strcasecmp((string) $completedStatus->getScope(), PackageTaskWorkflowStatus::STATUS_SCOPE) !== 0
        ) {
            throw new \InvalidArgumentException('Task completion must use the accepted workflow status.');
        }

        $project = $task->getWorkPackage()?->getProject();
        if ($project === null) {
            throw new \InvalidArgumentException('Task is not connected to a project.');
        }

        $account = $this->currentAccountProvider->getAccount();
        if ($account === null) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('A signed-in account is required.');
        }

        $this->workflowHandler->review($project, (string) $task->getId(), $account, 'accept');

        return $task;
    }
}