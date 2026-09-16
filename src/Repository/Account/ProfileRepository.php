<?php

namespace App\Repository\Account;

use App\Entity\Account\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    public function findLatestChanged(int $limit = 100) : array
    {
        return $this->createQueryBuilder('p')
                ->orderBy('p.lastModified', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
    }

}