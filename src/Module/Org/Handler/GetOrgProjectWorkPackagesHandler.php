<?php

namespace App\Module\Org\Handler;

use App\Module\Org\Mapper\WorkPackageMapper;
use App\Module\Org\Service\WorkPackageOverviewService;

class GetOrgProjectWorkPackagesHandler
{
    public function __construct(
        private readonly WorkPackageOverviewService $workPackageOverviewService,
        private readonly WorkPackageMapper $workPackageMapper,
    ) {
    }

    public function handle(string $projectId): array
    {
        $workPackages = $this->workPackageMapper->manyToArray(
            $this->workPackageOverviewService->getWorkPackagesForProjectId($projectId)
        );

        $taskCount = array_sum(array_column($workPackages, 'taskCount'));
        $completedTaskCount = array_sum(array_column($workPackages, 'completedTaskCount'));

        return [
            'workPackages' => $workPackages,
            'workPackageCount' => count($workPackages),
            'taskCount' => $taskCount,
            'averageProgress' => $taskCount > 0 ? (int) round(($completedTaskCount / $taskCount) * 100) : 0,
        ];
    }
}