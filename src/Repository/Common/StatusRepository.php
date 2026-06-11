<?php

namespace App\Repository\Common;

use App\Entity\Common\Status;
use Doctrine\ORM\EntityManagerInterface;

final class StatusRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findAll(): array
    {
        return $this->entityManager
            ->getRepository(Status::class)
            ->findAll();
    }

    public function findById(string $id): ?Status
    {
        return $this->entityManager
            ->getRepository(Status::class)
            ->find($id);
    }

    public function findByScope(string $scope): array
    {
        return $this->entityManager
            ->getRepository(Status::class)
            ->findBy([
                'scope' => $scope,
            ]);
    }

    public function findOneByNameAndScope(string $name, string $scope): ?Status
    {
        return $this->entityManager
            ->getRepository(Status::class)
            ->findOneBy([
                'name' => $name,
                'scope' => $scope,
            ]);
    }

    public function save(Status $status): void
    {
        $this->entityManager->persist($status);
        $this->entityManager->flush();
    }
}