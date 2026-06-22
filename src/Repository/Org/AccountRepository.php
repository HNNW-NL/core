<?php

namespace App\Repository\Org;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySearch(?string $search): array
    {
        if (!$search) {
            return $this->findAllForAdmin();
        }

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) LIKE LOWER(:search)')
            ->orWhere('LOWER(u.firstName) LIKE LOWER(:search)')
            ->orWhere('LOWER(u.lastName) LIKE LOWER(:search)')
            ->setParameter('search', '%' . strtolower($search) . '%')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}