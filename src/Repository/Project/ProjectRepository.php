<?php

namespace App\Repository\Project;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Security\ProjectPermission;
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

    public function organisationOverviewQuery(string $title, array $organisations, ?Account $account): QueryBuilder
    {
        $query = $this->createQueryBuilder('project')
            ->leftJoin('project.status', 'status')
            ->andWhere('project.deletedAt IS NULL');

        if ($organisations === [] || $account === null) {
            return $query->andWhere('1 = 0');
        }

        $query->innerJoin('project.ownerOrganisation', 'organisation')
            ->andWhere('organisation.deletedAt IS NULL')
            ->andWhere('organisation.id IN (:organisationIds)')
            ->setParameter('organisationIds', array_map(static fn ($organisation): string => $organisation->getId()->toRfc4122(), array_values($organisations)))
            ->andWhere("LOWER(project.title) LIKE :title ESCAPE '!'")
            ->setParameter('title', '%' . strtr(mb_strtolower(trim($title)), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%')
            ->setParameter('account', $account);

        if ($account->getProfile() === null) {
            return $query->andWhere('project.ownerAccount = :account');
        }

        // Match the voters: owner or latest active participant with the EDIT permission.
        $newerParticipant = $this->getEntityManager()->createQueryBuilder()
            ->select('newer.id')
            ->from(ProjectParticipant::class, 'newer')
            ->where('newer.project = project.id AND newer.profile = :profile')
            ->andWhere('newer.joinedAt IS NOT NULL AND newer.leftAt IS NULL')
            ->andWhere('(newer.joinedAt > participant.joinedAt OR (newer.joinedAt = participant.joinedAt AND newer.id > participant.id))');

        $editableParticipation = $this->getEntityManager()->createQueryBuilder()
            ->select('participant.id')
            ->from(ProjectParticipant::class, 'participant')
            ->innerJoin('participant.role', 'projectRole')
            ->where('participant.project = project.id AND participant.profile = :profile')
            ->andWhere('participant.joinedAt IS NOT NULL AND participant.leftAt IS NULL')
            ->andWhere('BIT_AND(projectRole.permissionsMask, :editPermission) = :editPermission')
            ->andWhere('NOT EXISTS (' . $newerParticipant->getDQL() . ')');

        return $query
            ->andWhere('project.ownerAccount = :account OR EXISTS (' . $editableParticipation->getDQL() . ')')
            ->setParameter('profile', $account->getProfile())
            ->setParameter('editPermission', ProjectPermission::EDIT->value);
    }

    public function findOrganisationOverviewPage(QueryBuilder $query, int $offset, int $limit): array
    {
        $participantCount = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(activeParticipant.id)')
            ->from(ProjectParticipant::class, 'activeParticipant')
            ->where('activeParticipant.project = project.id')
            ->andWhere('activeParticipant.joinedAt IS NOT NULL AND activeParticipant.leftAt IS NULL');

        $applicationCount = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(application.id)')
            ->from(ProjectApplication::class, 'application')
            ->innerJoin('application.status', 'applicationStatus')
            ->where('application.project = project.id AND application.deletedAt IS NULL')
            ->andWhere('LOWER(applicationStatus.name) IN (:submittedStatuses)');

        return $query
            ->addSelect('status', 'lead', 'leadProfile')
            ->leftJoin('project.ownerAccount', 'lead')
            ->leftJoin('lead.profile', 'leadProfile')
            ->addSelect('(' . $participantCount->getDQL() . ') AS participantCount')
            ->addSelect('(' . $applicationCount->getDQL() . ') AS applicationCount')
            ->setParameter('submittedStatuses', ['send', 'submitted'])
            ->orderBy('project.title', 'ASC')
            ->addOrderBy('project.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
