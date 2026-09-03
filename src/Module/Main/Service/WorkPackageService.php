<?php

namespace App\Module\Main\Service;

use App\Repository\Project\WorkPackageRepository;

class WorkPackageService
{
    public function __construct(
        private readonly WorkPackageRepository $workPackageRepository,
    ) {
    }

    public function getWorkPackagesForProjectSlug(string $projectSlug): array
    {
        return $this->workPackageRepository->findActiveByProjectSlug($projectSlug);
    }
}