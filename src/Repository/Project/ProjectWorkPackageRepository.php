<?php

namespace App\Repository\Project;

use App\Entity\Project\WorkPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkPackage>
 */
class ProjectWorkPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkPackage::class);
    }

    /**
     * @return list<WorkPackage>
     */
    public function findVisibleForProjectSlug(string $projectSlug): array
    {
        return $this->createQueryBuilder('workPackage')
            ->innerJoin('workPackage.project', 'project')
            ->addSelect('project')
            ->andWhere('project.slug = :projectSlug')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('workPackage.deletedAt IS NULL')
            ->setParameter('projectSlug', $projectSlug)
            ->orderBy('workPackage.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}