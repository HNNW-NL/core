<?php

namespace App\Module\Admin\Service;

use App\Module\Admin\DTO\StatusDTO;
use App\Repository\Common\StatusRepository;

final class StatusService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
    ) {
    }

    public function getAllStatuses(): array
    {
        return $this->statusRepository->findAll();
    }

    public function createStatus(StatusDTO $dto): void
    {
        $this->statusRepository->save();
    }

    public function updateStatus(StatusDTO $dto): void
    {
        $this->statusRepository->update();
    }
}