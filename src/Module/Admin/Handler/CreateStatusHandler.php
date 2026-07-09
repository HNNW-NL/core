<?php

namespace App\Module\Admin\Handler;

use App\Module\Admin\DTO\StatusDTO;
use App\Module\Admin\Service\StatusService;

final class CreateStatusHandler
{
    public function __construct(
        private readonly StatusService $statusService,
    ) {
    }

    public function handle(StatusDTO $dto): void
    {
        $this->statusService->createStatus($dto);
    }
}