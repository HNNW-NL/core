<?php

namespace App\Module\Admin\Handler;

use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\UpdateWorkPackageDTO;
use App\Module\Admin\Service\WorkPackageCrudService;

class UpdateWorkPackageHandler
{
    public function __construct(
        private readonly WorkPackageCrudService $workPackageCrudService,
    ) {
    }

    public function handle(UpdateWorkPackageDTO $dto): WorkPackage
    {
        return $this->workPackageCrudService->update($dto);
    }
}