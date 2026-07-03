<?php

namespace App\Module\Main\Handler;

use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Module\Main\Service\PackageTaskService;

class CompletePackageTaskHandler
{
    public function __construct(
        private readonly PackageTaskService $packageTaskService,
    ) {
    }

    public function handle(PackageTask $task, Status $completedStatus): PackageTask
    {
        return $this->packageTaskService->completeTask($task, $completedStatus);
    }
}