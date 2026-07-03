<?php

namespace App\Module\Main\Service;

use App\Module\Main\DTO\WorkPackageOverview;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectWorkPackageRepository;

final readonly class WorkPackageOverviewService
{
    public function __construct(
        private ProjectWorkPackageRepository $projectWorkPackageRepository,
        private PackageTaskRepository $packageTaskRepository,
    ) {
    }

    /**
     * @return list<WorkPackageOverview>
     */
    public function getForProjectSlug(string $projectSlug): array
    {
        $workPackages = $this->projectWorkPackageRepository->findVisibleForProjectSlug($projectSlug);
        $packageTasks = $this->packageTaskRepository->findVisibleForProjectSlug($projectSlug);
        $tasksByWorkPackageId = [];

        foreach ($packageTasks as $packageTask) {
            $workPackage = $packageTask->getWorkPackage();

            if ($workPackage === null) {
                continue;
            }

            $tasksByWorkPackageId[(string) $workPackage->getId()][] = $packageTask;
        }
        $overviews = [];

        foreach ($workPackages as $workPackage) {
            $workPackageId = (string) $workPackage->getId();

            $overviews[] = new WorkPackageOverview(
                $workPackage,
                $tasksByWorkPackageId[$workPackageId] ?? [],
            );
        }

        return $overviews;
    }
}