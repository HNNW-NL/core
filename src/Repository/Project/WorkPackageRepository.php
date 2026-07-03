<?php

namespace App\Repository\Project;

use App\Entity\Project\WorkPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class WorkPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkPackage::class);
    }

    public function findActiveByProjectId(string $projectId): array
    {
        return $this->createQueryBuilder('wp')
            ->andWhere('wp.project = :projectId')
            ->andWhere('wp.deletedAt IS NULL')
            ->setParameter('projectId', $projectId)
            ->orderBy('wp.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
