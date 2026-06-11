<?php

namespace App\Module\Admin\Service;

use App\Module\Admin\DTO\StatusDTO;
use App\Module\Admin\Mapper\StatusMapper;
use App\Repository\Common\StatusRepository;

final class StatusService
{
    public function __construct(
        private readonly StatusRepository $statusRepository,
        private readonly StatusMapper $statusMapper,
    ) {
    }

    public function getAllStatuses(): array
    {
        return $this->statusRepository->findAll();
    }

    public function createStatus(StatusDTO $dto): void
    {
        $status = $this->statusMapper->dtoToEntity($dto);

        $this->statusRepository->save($status);
    }

    public function updateStatus(StatusDTO $dto): void
    {
        // later implementeren
    }
}