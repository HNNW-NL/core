<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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

    public function findOneActiveById(Uuid $id): ?Project
    {
        return $this->createQueryBuilder('project')
            ->andWhere('project.id = :id')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByProfileOnQuery(string $query, Profile $profile): int
    {
        return (int) $this->createProfileQueryBuilder($query, $profile)
            ->select('COUNT(DISTINCT project.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPaginatedByProfileOnQuery(string $query, Profile $profile, int $maxResultsPerPage, int $page): array
    {
        return $this->createProfileQueryBuilder($query, $profile)
            ->orderBy('project.title', 'ASC')
            ->setMaxResults($maxResultsPerPage)
            ->setFirstResult(($page - 1) * $maxResultsPerPage)
            ->getQuery()
            ->getResult();
    }

    private function createProfileQueryBuilder(string $query, Profile $profile): QueryBuilder
    {
        $qb = $this->createQueryBuilder('project')
            ->distinct()
            ->innerJoin('project.participants', 'participant')
            ->andWhere('participant.profile = :profile')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('profile', $profile);

        $query = trim($query);

        if ($query !== '') {
            $qb
                ->andWhere('LOWER(project.title) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        return $qb;
    }


    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     organisationName: string,
     *     location: string|null,
     *     projectStatus: string,
     *     remotePossible: bool,
     *     canApply: bool|int|string
     * }>
     */
    public function findPublicProjects(?int $limit = null): array
    {
        $queryBuilder = $this->createPublicProjectsQueryBuilder();
        $queryBuilder
            ->orderBy('project.publishedAt', 'DESC')
            ->addOrderBy('project.id', 'ASC');

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $queryBuilder->getQuery()->getScalarResult();
    }

    /**
     * @return list<array{
     *     slug: string,
     *     title: string,
     *     summary: string,
     *     organisationName: string,
     *     location: string|null,
     *     projectStatus: string,
     *     remotePossible: bool,
     *     canApply: bool|int|string
     * }>
     */
    public function findPaginatedPublicProjects(
        string $search,
        bool $onlyAcceptingApplications,
        int $page,
        int $perPage,
    ): array {
        return $this->createPublicProjectsQueryBuilder($search, $onlyAcceptingApplications)
            ->orderBy('project.publishedAt', 'DESC')
            ->addOrderBy('project.id', 'ASC')
            ->setFirstResult((max(1, $page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getScalarResult();
    }

    public function countPublicProjects(
        string $search,
        bool $onlyAcceptingApplications,
    ): int {
        return (int) $this->createPublicProjectsQueryBuilder(
            $search,
            $onlyAcceptingApplications,
            false,
        )
            ->select('COUNT(DISTINCT project.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createPublicProjectsQueryBuilder(
        string $search = '',
        bool $onlyAcceptingApplications = false,
        bool $includeAvailability = true,
    ): QueryBuilder {
        $now = new \DateTimeImmutable();
        $activeParticipantCount = static fn (string $participantAlias): string =>
            '(SELECT COUNT(' . $participantAlias . '.id)
                FROM App\Entity\Project\ProjectParticipant ' . $participantAlias . '
                WHERE ' . $participantAlias . '.project = project
                    AND ' . $participantAlias . '.joinedAt IS NOT NULL
                    AND ' . $participantAlias . '.leftAt IS NULL
            )';

        $queryBuilder = $this->createQueryBuilder('project')
            ->select(
                'project.slug AS slug',
                'project.title AS title',
                'project.summary AS summary',
                'ownerOrganisation.name AS organisationName',
                'ownerOrganisation.location AS location',
                'status.name AS projectStatus',
                'project.remotePossible AS remotePossible'
            )
            ->innerJoin('project.status', 'status')
            ->innerJoin('project.ownerOrganisation', 'ownerOrganisation')
            ->andWhere('project.startDate <= :now')
            ->andWhere('(project.endDate IS NULL OR project.endDate >= :now)')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('project.visibility = :visibility')
            ->andWhere('status.scope = :statusScope')
            ->andWhere('status.name = :statusName')
            ->setParameter('now', $now)
            ->setParameter('visibility', 'public')
            ->setParameter('statusScope', 'project')
            ->setParameter('statusName', 'published');

        if ($includeAvailability) {
            $availabilityParticipantCount = $activeParticipantCount(
                'availabilityParticipant',
            );
            $queryBuilder->addSelect(
                'CASE WHEN project.capacity IS NULL OR '
                    . $availabilityParticipantCount
                    . ' < project.capacity THEN 1 ELSE 0 END AS canApply'
            );
        }

        $search = trim($search);
        if ($search !== '') {
            $queryBuilder
                ->andWhere('(LOWER(project.title) LIKE :search OR LOWER(project.summary) LIKE :search)')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        if ($onlyAcceptingApplications) {
            $filterParticipantCount = $activeParticipantCount('filterParticipant');
            $queryBuilder->andWhere(
                '(project.capacity IS NULL OR ' . $filterParticipantCount . ' < project.capacity)'
            );
        }

        return $queryBuilder;
    }
}
