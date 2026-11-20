<?php

namespace App\Module\Admin\Handler;

use App\Entity\Project\WorkPackage;
use App\Entity\Project\Project;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Main\Service\WorkPackageCrudService;

class CreateWorkPackageHandler
{
    public function __construct(
        private readonly WorkPackageCrudService $workPackageCrudService,
    ) {
    }

    public function handle(CreateWorkPackageDTO $dto, ?Project $project = null): WorkPackage
    {
        return $this->workPackageCrudService->create($dto, $project);
    }
}
