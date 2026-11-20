<?php

namespace App\Module\Admin\Handler;

use App\Entity\Project\Project;
use App\Module\Main\Service\WorkPackageCrudService;

class DeleteWorkPackageHandler
{
    public function __construct(
        private readonly WorkPackageCrudService $workPackageCrudService,
    ) {
    }

    public function handle(string $workPackageId, ?Project $project = null): void
    {
        $this->workPackageCrudService->delete($workPackageId, $project);
    }
}
