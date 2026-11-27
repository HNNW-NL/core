<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectApplication;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectParticipant>
 */
class ProjectParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectParticipant::class);
    }

    public static function restrictToActive(QueryBuilder $query, string $alias): QueryBuilder
    {
        return $query
            ->innerJoin($alias . '.status', $alias . 'Status')
            ->innerJoin($alias . '.profile', $alias . 'Profile')
            ->innerJoin($alias . 'Profile.account', $alias . 'Account')
            ->andWhere($alias . '.joinedAt IS NOT NULL AND ' . $alias . '.leftAt IS NULL')
            ->andWhere($alias . 'Profile.deletedAt IS NULL AND ' . $alias . 'Account.deletedAt IS NULL')
            ->andWhere(sprintf("%sStatus.scope = 'ProjectParticipant' AND LOWER(%sStatus.name) = 'active'", $alias, $alias));
    }

    public function findActiveParticipant(
        Project $project,
        Profile $profile
    ): ?ProjectParticipant
    {
        return self::restrictToActive($this->createQueryBuilder('participant'), 'participant')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.profile = :profile')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getOneOrNullResult();
    }
    public function findActiveParticipantsByProjectAndProfile(Project $project, Profile $profile): array
    {
        return self::restrictToActive($this->createQueryBuilder('pp'), 'pp')
            ->andWhere('pp.project = :project')
            ->andWhere('pp.profile = :profile')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->orderBy('pp.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestActiveParticipantByProjectAndProfile(Project $project, Profile $profile): ?ProjectParticipant
    {
        return self::restrictToActive($this->createQueryBuilder('pp'), 'pp')
            ->andWhere('pp.project = :project')
            ->andWhere('pp.profile = :profile')
            ->setParameter('project', $project->getId(), 'ulid')
            ->setParameter('profile', $profile->getId(), 'ulid')
            ->orderBy('pp.joinedAt', 'DESC')
            ->addOrderBy('pp.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isActiveParticipant(Project $project, Profile $profile): bool
    {
        return $this->findActiveParticipantsByProjectAndProfile($project, $profile) !== [];
    }

    /**
     * OR06-A: alle (niet verwijderde) aanvragen van een project, nieuwste eerst.
     *
     * @return list<ProjectApplication>
     */
    public function findApplicationsForProject(Project $project): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('application', 'profile', 'status')
            ->from(ProjectApplication::class, 'application')
            ->innerJoin('application.profile', 'profile')
            ->innerJoin('application.status', 'status')
            ->andWhere('application.project = :project')
            ->andWhere('application.deletedAt IS NULL')
            ->setParameter('project', $project)
            ->orderBy('application.lastModified', 'DESC')
            ->addOrderBy('application.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * OR06-A: één aanvraag binnen een specifiek project, zodat een organisatie
     * nooit een aanvraag van een ander project kan behandelen.
     */
    public function findApplicationForProject(string $applicationId, Project $project, bool $lockForUpdate = false): ?ProjectApplication
    {
        if (!Ulid::isValid($applicationId)) {
            return null;
        }

        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('application')
            ->from(ProjectApplication::class, 'application')
            ->andWhere('application.id = :id')
            ->andWhere('application.project = :project')
            ->andWhere('application.deletedAt IS NULL')
            ->setParameter('id', Ulid::fromString($applicationId), 'ulid')
            ->setParameter('project', $project)
            ->getQuery();

        // OR06-B: binnen een transactie blokkeert dit een gelijktijdige tweede behandeling van dezelfde aanvraag.
        if ($lockForUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getOneOrNullResult();
    }
}
