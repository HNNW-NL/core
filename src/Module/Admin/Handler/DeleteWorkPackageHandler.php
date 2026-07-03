<?php

namespace App\Module\Admin\Handler;

use App\Module\Admin\Service\WorkPackageCrudService;

class DeleteWorkPackageHandler
{
    public function __construct(
        private readonly WorkPackageCrudService $workPackageCrudService,
    ) {
    }

    public function handle(string $workPackageId): void
    {
        $this->workPackageCrudService->delete($workPackageId);
    }
}