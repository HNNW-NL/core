<?php

namespace App\Module\Main\DTO;

use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;

final readonly class WorkPackageOverview
{
    /**
     * @param list<PackageTask> $packageTasks
     */
    public function __construct(
        public WorkPackage $workPackage,
        public array $packageTasks = [],
    ) {
    }
}