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

    public function getStatusesByScope(string $scope): array
    {
        return $this->statusRepository->findByScope($scope);
    }

    public function statusExists(string $name, string $scope): bool
    {
        return $this->statusRepository
            ->findOneByNameAndScope($name, $scope) !== null;
    }

    public function getStatusById(string $id)
    {
        return $this->statusRepository->findById($id);
    }

    public function createStatus(StatusDTO $dto): void
    {
        $status = $this->statusMapper->dtoToEntity($dto);

        $this->statusRepository->save($status);
    }

    public function updateStatus(string $id, StatusDTO $dto): void
    {
        $status = $this->statusRepository->findById($id);

        if (!$status) {
            return;
        }

        $status->setName($dto->name);
        $status->setColourHex($dto->colourHex);
        $status->setScope($dto->scope);

        $this->statusRepository->flush();
    }
    public function deleteStatus(string $id): void
    {
    $status = $this->statusRepository->findById($id);

    if (!$status) {
        return;
    }

    $this->statusRepository->remove($status);
  }
}