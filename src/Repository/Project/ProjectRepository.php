<?php

namespace App\Repository\Project;

use Symfony\Bridge\Doctrine\Types\UlidType;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
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

    public function findOneActiveById(Ulid $id): ?Project
    {
        return $this->createQueryBuilder('project')
            ->andWhere('project.id = :id')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('id', $id, UlidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByProfileOnQuery(string $query, Profile $profile, bool $activeOnly = false): int
    {
        return (int) $this->createProfileQueryBuilder($query, $profile, $activeOnly)
            ->select('COUNT(DISTINCT project.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPaginatedByProfileOnQuery(string $query, Profile $profile, int $maxResultsPerPage, int $page, bool $activeOnly = false): array
    {
        return $this->createProfileQueryBuilder($query, $profile, $activeOnly)
            ->orderBy('project.title', 'ASC')
            ->setMaxResults($maxResultsPerPage)
            ->setFirstResult(($page - 1) * $maxResultsPerPage)
            ->getQuery()
            ->getResult();
    }

    private function createProfileQueryBuilder(string $query, Profile $profile, bool $activeOnly = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('project')
            ->distinct()
            ->innerJoin('project.participants', 'participant')
            ->andWhere('participant.profile = :profile')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('profile', $profile->getId(), UlidType::NAME);

        if ($activeOnly) {
            $qb->innerJoin('participant.profile', 'profile')
                ->innerJoin('participant.status', 'participantStatus')->innerJoin('project.status', 'projectStatus')
                ->andWhere('profile.deletedAt IS NULL')->andWhere('participant.leftAt IS NULL')
                ->andWhere('LOWER(participantStatus.scope) = :participantScope')->setParameter('participantScope', 'projectparticipant')
                ->andWhere('LOWER(projectStatus.scope) = :projectScope')->setParameter('projectScope', 'project')
                ->andWhere('LOWER(participantStatus.name) IN (:active)')->andWhere('LOWER(projectStatus.name) IN (:active)')
                ->setParameter('active', ['active', 'actief']);
        }

        $query = trim($query);

        if ($query !== '') {
            $qb
                ->andWhere('LOWER(project.title) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        return $qb;
    }

}
