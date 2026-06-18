<?php

namespace App\Module\Org\Handler;

use App\Entity\Project\WorkPackage;
use App\Module\Org\DTO\CreateWorkPackageDTO;
use App\Module\Org\Service\WorkPackageCrudService;

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