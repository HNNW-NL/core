<?php

namespace App\Module\Admin\Handler;

use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Admin\Service\PackageTaskCrudService;

class CreatePackageTaskHandler
{
    public function __construct(
        private readonly PackageTaskCrudService $packageTaskCrudService,
    ) {
    }

    public function handle(CreatePackageTaskDTO $dto): void
    {
        $this->packageTaskCrudService->create($dto);
    }
}
