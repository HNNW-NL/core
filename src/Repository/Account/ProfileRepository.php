<?php

namespace App\Repository\Account;

use App\Entity\Account\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('LOWER(p.displayName) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();
    }

    public function searchExcluding(string $query, array $excludedProfileIds): array
    {
        return $this->createQueryBuilder('p')
            ->where('LOWER(p.displayName) LIKE LOWER(:query)')
            ->andWhere('p.id NOT IN (:existingProfileIds)')
            ->setParameter('existingProfileIds', $excludedProfileIds)
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();
    }

}
