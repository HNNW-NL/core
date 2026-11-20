<?php

namespace App\Module\Admin\Handler;

use App\Entity\Project\WorkPackage;
use App\Entity\Project\Project;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;
use App\Module\Main\Service\WorkPackageCrudService;

class UpdateWorkPackageHandler
{
    public function __construct(
        private readonly WorkPackageCrudService $workPackageCrudService,
    ) {
    }

    public function handle(UpdateWorkPackageDTO $dto, ?Project $project = null): WorkPackage
    {
        return $this->workPackageCrudService->update($dto, $project);
    }
}
