<?php

namespace App\Module\Admin\Handler;

use App\Entity\Project\WorkPackage;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Admin\Service\WorkPackageCrudService;

class CreateWorkPackageHandler
{
    public function __construct(
        private readonly WorkPackageCrudService $workPackageCrudService,
    ) {
    }

    public function handle(CreateWorkPackageDTO $dto): WorkPackage
    {
        return $this->workPackageCrudService->create($dto);
    }
}