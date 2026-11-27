<?php

namespace App\Repository\Project;

use App\Security\ProjectParticipation;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectApplication;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
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

    private function activeFor(Project $project, Profile $profile): \Doctrine\ORM\QueryBuilder
    {
        return self::restrictToActive($this->createQueryBuilder('pp'), 'pp')
            ->andWhere('pp.project = :project')->andWhere('pp.profile = :profile')
            ->setParameter('project', $project->getId(), 'ulid')->setParameter('profile', $profile->getId(), 'ulid');
    }

    public static function restrictToActive(QueryBuilder $query, string $alias): QueryBuilder
    {
        return ProjectParticipation::restrictQuery($query, $alias);
    }

    public function findActiveParticipant(Project $project, Profile $profile): ?ProjectParticipant
    {
        return $this->findLatestActiveParticipantByProjectAndProfile($project, $profile);
    }

    public function findActiveParticipantsByProjectAndProfile(Project $project, Profile $profile): array
    {
        return $this->activeFor($project, $profile)->orderBy('pp.joinedAt', 'DESC')->addOrderBy('pp.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function findLatestActiveParticipantByProjectAndProfile(Project $project, Profile $profile): ?ProjectParticipant
    {
        return $this->activeFor($project, $profile)->orderBy('pp.joinedAt', 'DESC')->addOrderBy('pp.id', 'DESC')
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    public function isActiveParticipant(Project $project, Profile $profile): bool
    {
        return (int) $this->activeFor($project, $profile)->select('COUNT(pp.id)')->getQuery()->getSingleScalarResult() > 0;
    }

    public function countActiveProfiles(Project $project): int
    {
        return (int) self::restrictToActive($this->createQueryBuilder('participant'), 'participant')
            ->select('COUNT(DISTINCT participantProfile.id)')
            ->andWhere('participant.project = :project')->setParameter('project', $project->getId(), 'ulid')
            ->getQuery()->getSingleScalarResult();
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
            ->setParameter('project', $project->getId(), 'ulid')
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
            ->setParameter('project', $project->getId(), 'ulid')
            ->getQuery();

        // OR06-B: binnen een transactie blokkeert dit een gelijktijdige tweede behandeling van dezelfde aanvraag.
        if ($lockForUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
            $query->setHint(Query::HINT_REFRESH, true);
        }

        return $query->getOneOrNullResult();
    }
}
