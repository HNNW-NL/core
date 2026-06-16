<?php

namespace App\Module\Main\Handler;

use App\Entity\Account\Profile;
use App\Entity\Project\PackageTask;
use App\Module\Main\Service\PackageTaskService;

class SelfAssignPackageTaskHandler
{
    public function __construct(
        private readonly PackageTaskService $packageTaskService,
    ) {
    }

    public function handle(PackageTask $task, Profile $profile): PackageTask
    {
        return $this->packageTaskService->selfAssignTask($task, $profile);
    }
}