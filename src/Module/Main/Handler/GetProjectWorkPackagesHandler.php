<?php

namespace App\Module\Main\Handler;

use App\Module\Main\Mapper\WorkPackageMapper;
use App\Module\Main\Service\WorkPackageService;

class GetProjectWorkPackagesHandler
{
    public function __construct(
        private readonly WorkPackageService $workPackageService,
        private readonly WorkPackageMapper $workPackageMapper,
    ) {
    }

    public function handle(string $projectSlug): array
    {
        $workPackages = $this->workPackageService->getWorkPackagesForProjectSlug($projectSlug);

        return $this->workPackageMapper->manyToArray($workPackages);
    }
}