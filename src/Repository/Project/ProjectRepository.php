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

    /**
     * @return list<Project>
     */
    public function findOpenForHomepage(int $limit = 6): array
    {
        return $this->createQueryBuilder('project')
            ->innerJoin('project.status', 'status')
            ->addSelect('status')
            ->innerJoin('project.ownerOrganisation', 'ownerOrganisation')
            ->addSelect('ownerOrganisation')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('project.visibility = :visibility')
            ->andWhere('status.scope = :statusScope')
            ->andWhere('status.name = :statusName')
            ->setParameter('visibility', 'public')
            ->setParameter('statusScope', 'project')
            ->setParameter('statusName', 'published')
            ->orderBy('project.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}