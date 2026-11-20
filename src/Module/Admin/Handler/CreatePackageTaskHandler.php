<?php

namespace App\Module\Admin\Handler;

use App\Entity\Project\Project;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Main\Service\PackageTaskCrudService;

class CreatePackageTaskHandler
{
    public function __construct(
        private readonly PackageTaskCrudService $packageTaskCrudService,
    ) {
    }

    public function handle(CreatePackageTaskDTO $dto, ?Project $project = null): void
    {
        $this->packageTaskCrudService->create($dto, $project);
    }
}
