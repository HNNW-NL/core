<?php

namespace App\Module\Main\Handler;

use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Module\Main\Service\WorkPackageWorkflowService;

final class ChangeWorkPackageStatusHandler
{
    public function __construct(
        private readonly WorkPackageWorkflowService $workflowService,
    ) {}

    public function handle(
        ChangeWorkPackageStatusDTO $dto
    ): void {
        // TODO:
        // WorkPackage ophalen
        // Huidige status ophalen
        // Workflow controleren
        // Nieuwe status opslaan
    }
}