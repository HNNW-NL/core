<?php

namespace App\Repository\Project;

use App\Entity\Project\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
            ->innerJoin('project.ownerOrganisation', 'ownerOrganisation')
            ->andWhere('project.slug = :slug')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('ownerOrganisation.deletedAt IS NULL')
            ->andWhere('LOWER(project.visibility) = :visibility')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->setParameter('slug', $slug)
            ->setParameter('visibility', 'public')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array{
     *     items: list<array{title: ?string, summary: ?string, slug: ?string, remotePossible: bool}>,
     *     total: int,
     *     page: int,
     *     pages: int
     * }
     */
    public function findPublicPage(string $search, ?bool $remotePossible, int $page, int $perPage): array
    {
        $perPage = max(1, min(50, $perPage));
        $queryBuilder = $this->createPublicProjectsQueryBuilder($search, $remotePossible);

        $countQueryBuilder = clone $queryBuilder;
        $total = (int) $countQueryBuilder
            ->select('COUNT(project.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $items = $queryBuilder
            ->select(
                'project.title AS title',
                'project.summary AS summary',
                'project.slug AS slug',
                'project.remotePossible AS remotePossible',
            )
            ->orderBy('project.publishedAt', 'DESC')
            ->addOrderBy('project.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getArrayResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    private function createPublicProjectsQueryBuilder(string $search, ?bool $remotePossible): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('project')
            ->innerJoin('project.ownerOrganisation', 'ownerOrganisation')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('ownerOrganisation.deletedAt IS NULL')
            ->andWhere('LOWER(project.visibility) = :visibility')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->setParameter('visibility', 'public');

        if ($search !== '') {
            $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], strtolower($search));
            $queryBuilder
                ->andWhere("(LOWER(project.title) LIKE :search ESCAPE '!' OR LOWER(project.summary) LIKE :search ESCAPE '!')")
                ->setParameter('search', '%' . $escapedSearch . '%');
        }

        if ($remotePossible !== null) {
            $queryBuilder
                ->andWhere('project.remotePossible = :remotePossible')
                ->setParameter('remotePossible', $remotePossible);
        }

        return $queryBuilder;
    }
}