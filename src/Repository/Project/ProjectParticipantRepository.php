<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectParticipant>
 */
class ProjectParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectParticipant::class);
    }

    public function findActiveParticipant(
        Project $project,
        Profile $profile
    ): ?ProjectParticipant {
        return $this->createQueryBuilder('participant')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.profile = :profile')
            ->andWhere('participant.joinedAt IS NOT NULL')
            ->andWhere('participant.leftAt IS NULL')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isActiveParticipant(
        Project $project,
        Profile $profile
    ): bool {
        return $this->findActiveParticipant($project, $profile) !== null;
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
            ->orderBy('application.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * OR06-A: één aanvraag binnen een specifiek project, zodat een organisatie
     * nooit een aanvraag van een ander project kan behandelen.
     */
    public function findApplicationForProject(string $applicationId, Project $project, bool $lockForUpdate = false): ?ProjectApplication
    {
        if (!Uuid::isValid($applicationId)) {
            return null;
        }

        $query = $this->getEntityManager()->createQueryBuilder()
            ->select('application')
            ->from(ProjectApplication::class, 'application')
            ->andWhere('application.id = :id')
            ->andWhere('application.project = :project')
            ->andWhere('application.deletedAt IS NULL')
            ->setParameter('id', Uuid::fromString($applicationId), 'uuid')
            ->setParameter('project', $project)
            ->getQuery();

        // OR06-B: binnen een transactie blokkeert dit een gelijktijdige tweede behandeling van dezelfde aanvraag.
        if ($lockForUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return $query->getOneOrNullResult();
    }
}