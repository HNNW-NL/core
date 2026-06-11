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

    public function save(Status $status): void
    {
        $this->entityManager->persist($status);
        $this->entityManager->flush();
    }
}