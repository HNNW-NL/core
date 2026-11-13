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

    public function getCompletedTaskCount(): int
    {
        $completedStatuses = ['gereed', 'done', 'completed', 'complete'];
        $completedTaskCount = 0;

        foreach ($this->packageTasks as $packageTask) {
            $statusName = strtolower($packageTask->getStatus()?->getName() ?? '');

            if (in_array($statusName, $completedStatuses, true)) {
                $completedTaskCount++;
            }
        }

        return $completedTaskCount;
    }
}