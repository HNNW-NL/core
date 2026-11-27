<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Org\Organisation;
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
            ->andWhere('project.visibility = :visibility')
            ->setParameter('slug', $slug)
            ->setParameter('visibility', 'public')
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

    public function findByProfile(Profile $profile): array
    {
        return $this->createQueryBuilder('project')
            ->innerJoin('project.participants', 'participant')
            ->andWhere('participant.profile = :profile')
            ->setParameter('profile', $profile->getId(), 'ulid')
            ->orderBy('project.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByOrganisationOnQuery(string $query, Organisation $organisation): int
    {
        return (int) $this->createOrganisationQueryBuilder($query, $organisation)
            ->select('COUNT(project.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPaginatedByOrganisationOnQuery(
        string $query,
        Organisation $organisation,
        int $maxResultsPerPage,
        int $page,
    ): array {
        return $this->createOrganisationQueryBuilder($query, $organisation)
            ->orderBy('project.title', 'ASC')
            ->addOrderBy('project.id', 'ASC')
            ->setMaxResults($maxResultsPerPage)
            ->setFirstResult(($page - 1) * $maxResultsPerPage)
            ->getQuery()
            ->getResult();
    }

    private function createOrganisationQueryBuilder(
        string $query,
        Organisation $organisation,
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('project')
            ->andWhere('project.ownerOrganisation = :organisation')
            ->andWhere('project.deletedAt IS NULL')
            ->setParameter('organisation', $organisation->getId(), 'ulid');

        $query = trim($query);

        if ($query !== '') {
            $qb
                ->andWhere('(LOWER(project.title) LIKE :query OR LOWER(project.summary) LIKE :query)')
                ->setParameter('query', '%' . mb_strtolower($query) . '%');
        }

        return $qb;
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

}
