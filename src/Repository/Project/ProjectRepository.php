<?php

namespace App\Repository\Project;

use App\Entity\Project\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findOneVisibleBySlug(string $slug): ?Project
    {
        return $this->createQueryBuilder('project')
            ->andWhere('project.slug = :slug')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOnePublicBySlug(string $slug): ?Project
    {
        return $this->createQueryBuilder('project')
            ->andWhere('project.slug = :slug')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('project.visibility IN (:visibilities)')
            ->setParameter('slug', $slug)
            ->setParameter('visibilities', ['public', 'unlisted'])
            ->getQuery()
            ->getOneOrNullResult();
    }
}