<?php

namespace App\Repository\Project;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
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

    public function findActiveParticipant(Project $project, Profile $profile): ?ProjectParticipant
    {
        return $this->createQueryBuilder('participant')
            ->innerJoin('participant.status', 'status')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.profile = :profile')
            ->andWhere('participant.leftAt IS NULL')
            ->andWhere('LOWER(status.name) = :active')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->setParameter('active', 'active')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isActiveParticipant(Project $project, Profile $profile): bool
    {
        return $this->findActiveParticipant($project, $profile) instanceof ProjectParticipant;
    }

    public function countActiveForProject(Project $project): int
    {
        return (int) $this->createQueryBuilder('participant')
            ->select('COUNT(participant.id)')
            ->innerJoin('participant.status', 'status')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.leftAt IS NULL')
            ->andWhere('LOWER(status.name) = :active')
            ->setParameter('project', $project)
            ->setParameter('active', 'active')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
