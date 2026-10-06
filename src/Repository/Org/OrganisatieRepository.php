<?php

namespace App\Repository\Org;

use App\Entity\Org\Organisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrganisatieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organisation::class);
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->Where('o.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
