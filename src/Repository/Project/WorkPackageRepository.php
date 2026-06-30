<?php

namespace App\Repository\Project;

use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WorkPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkPackage::class);
    }

    /**
     * @return WorkPackage[]
     */
    public function findActiveByProjectSlug(string $projectSlug): array
    {
        return $this->createQueryBuilder('workPackage')
            ->innerJoin('workPackage.project', 'project')
            ->andWhere('project.slug = :projectSlug')
            ->andWhere('workPackage.deletedAt IS NULL')
            ->setParameter('projectSlug', $projectSlug)
            ->orderBy('workPackage.dueDate', 'ASC')
            ->addOrderBy('workPackage.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WorkPackage[]
     */
    public function findActiveByProject(Project $project): array
    {
        return $this->createQueryBuilder('workPackage')
            ->andWhere('workPackage.project = :project')
            ->andWhere('workPackage.deletedAt IS NULL')
            ->setParameter('project', $project)
            ->orderBy('workPackage.dueDate', 'ASC')
            ->addOrderBy('workPackage.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}