<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

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
            ->innerJoin('project.status', 'status')
            ->andWhere('LOWER(status.name) <> :archivedStatus')
            ->setParameter('archivedStatus', 'archived')
            ->andWhere('project.slug = :slug')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('ownerOrganisation.deletedAt IS NULL')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.visibility IN (:visibilities)')
            ->setParameter('slug', $slug)
            ->setParameter('visibilities', ['public'])
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function canAcceptApplications(Project $project): bool
    {
        $now = new \DateTimeImmutable();
        $status = $project->getStatus();

        if (
            strtolower(trim((string) $status?->getName())) !== 'published'
            || $status?->getScope() !== 'project'
            || $project->getPublishedAt() === null
            || $project->getDeletedAt() !== null
            || !in_array($project->getVisibility(), ['public', 'unlisted'], true)
            || $project->getStartDate() > $now
            || ($project->getEndDate() !== null && $project->getEndDate() < $now)
        ) {
            return false;
        }

        $capacity = $project->getCapacity();
        if ($capacity === null) {
            return true;
        }

        $activeParticipants = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(participant.id)')
            ->from(ProjectParticipant::class, 'participant')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.joinedAt IS NOT NULL')
            ->andWhere('participant.leftAt IS NULL')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return $activeParticipants < $capacity;
    }

    public function findOneUnpublishedBySlug(string $slug): ?Project
    {
        return $this->createQueryBuilder('project')
            ->andWhere('project.slug = :slug')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('project.publishedAt IS NULL')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneActiveById(Ulid $id): ?Project
    {
        return $this->createQueryBuilder('project')
            ->andWhere('project.id = :id')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('id', $id, 'ulid')
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
            ->setParameter('profile', $profile->getId(), 'ulid');

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
     *     canApply: bool|int|string,
     *     skills: list<string>
     * }>
     */
    public function findPublicProjects(?int $limit = null): array
    {
        $queryBuilder = $this->createPublicProjectsQueryBuilder('', true);
        $queryBuilder
            ->orderBy('canApply', 'DESC')
            ->addOrderBy('project.publishedAt', 'DESC')
            ->addOrderBy('project.id', 'ASC');

        if ($limit !== null) {
            $queryBuilder->setMaxResults($limit);
        }

        return $this->includePublicProjectSkills($queryBuilder->getQuery()->getScalarResult());
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
     *     canApply: bool|int|string,
     *     skills: list<string>
     * }>
     */
    public function findPaginatedPublicProjects(
        string $search,
        bool $onlyAcceptingApplications,
        int $page,
        int $perPage,
        ?bool $remotePossible = null,
    ): array {
        $projects = $this->createPublicProjectsQueryBuilder(
            $search,
            $onlyAcceptingApplications,
            true,
            $remotePossible,
        )
            ->orderBy('canApply', 'DESC')
            ->addOrderBy('project.publishedAt', 'DESC')
            ->addOrderBy('project.id', 'ASC')
            ->setFirstResult((max(1, $page) - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getScalarResult();

        return $this->includePublicProjectSkills($projects);
    }

    public function countPublicProjects(
        string $search,
        bool $onlyAcceptingApplications,
        ?bool $remotePossible = null,
    ): int {
        return (int) $this->createPublicProjectsQueryBuilder(
            $search,
            $onlyAcceptingApplications,
            false,
            $remotePossible,
        )
            ->select('COUNT(DISTINCT project.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createPublicProjectsQueryBuilder(
        string $search = '',
        bool $onlyAcceptingApplications = false,
        bool $includeAvailability = true,
        ?bool $remotePossible = null,
    ): QueryBuilder {
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
            ->andWhere('LOWER(status.name) <> :archivedStatus')
            ->setParameter('archivedStatus', 'archived')
            ->innerJoin('project.ownerOrganisation', 'ownerOrganisation')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('ownerOrganisation.deletedAt IS NULL')
            ->andWhere('project.visibility = :visibility')
            ->setParameter('visibility', 'public');

        if ($includeAvailability || $onlyAcceptingApplications) {
            $queryBuilder->setParameter(
                'applicationDate',
                new \DateTimeImmutable('today')
            );
        }

        $availabilityConditions =
            "(LOWER(status.name) IN ('active', 'published')"
            . " AND (project.endDate IS NULL OR project.endDate >= :applicationDate))";

        if ($includeAvailability) {
            $availabilityParticipantCount = $activeParticipantCount(
                'availabilityParticipant',
            );
            $queryBuilder->addSelect(
                'CASE WHEN ' . $availabilityConditions . ' AND '
                    . '(project.capacity IS NULL OR '
                    . $availabilityParticipantCount
                    . ' < project.capacity) THEN 1 ELSE 0 END AS canApply'
            );
        }

        $search = trim($search);
        if ($search !== '') {
            $escapedSearch = str_replace(
                ['!', '%', '_'],
                ['!!', '!%', '!_'],
                mb_strtolower($search),
            );
            $queryBuilder
                ->andWhere("(LOWER(project.title) LIKE :search ESCAPE '!' OR LOWER(project.summary) LIKE :search ESCAPE '!')")
                ->setParameter('search', '%' . $escapedSearch . '%');
        }

        if ($remotePossible !== null) {
            $queryBuilder
                ->andWhere('project.remotePossible = :remotePossible')
                ->setParameter('remotePossible', $remotePossible);
        }

        if ($onlyAcceptingApplications) {
            $queryBuilder->andWhere($availabilityConditions);
            $filterParticipantCount = $activeParticipantCount('filterParticipant');
            $queryBuilder->andWhere(
                '(project.capacity IS NULL OR '
                    . $filterParticipantCount
                    . ' < project.capacity)'
            );
        }

        return $queryBuilder;
    }

    /**
     * @param list<array<string, mixed>> $projects
     * @return list<array<string, mixed>>
     */
    private function includePublicProjectSkills(array $projects): array
    {
        if ($projects === []) {
            return [];
        }

        $slugs = array_column($projects, 'slug');
        $skillRows = $this->createQueryBuilder('project')
            ->select('project.slug AS slug', 'skill.name AS skillName')
            ->innerJoin('project.skills', 'projectSkill')
            ->innerJoin('projectSkill.skill', 'skill')
            ->innerJoin('project.status', 'status')
            ->andWhere('LOWER(status.name) <> :archivedStatus')
            ->setParameter('archivedStatus', 'archived')
            ->innerJoin('project.ownerOrganisation', 'ownerOrganisation')
            ->andWhere('project.slug IN (:slugs)')
            ->andWhere('project.publishedAt IS NOT NULL')
            ->andWhere('project.deletedAt IS NULL')
            ->andWhere('ownerOrganisation.deletedAt IS NULL')
            ->andWhere('project.visibility = :visibility')
            ->setParameter('slugs', $slugs)
            ->setParameter('visibility', 'public')
            ->orderBy('skill.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $skillsBySlug = [];
        foreach ($skillRows as $row) {
            $skillsBySlug[$row['slug']][] = $row['skillName'];
        }

        foreach ($projects as &$project) {
            $project['skills'] = $skillsBySlug[$project['slug']] ?? [];
        }
        unset($project);

        return $projects;
    }
}
