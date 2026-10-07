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

    public function findActiveParticipantsByProjectAndProfile(Project $project, Profile $profile): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.project = :project')
            ->andWhere('pp.profile = :profile')
            ->andWhere('pp.leftAt IS NULL')
            ->andWhere('pp.joinedAt IS NOT NULL')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->orderBy('pp.joinedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestActiveParticipantByProjectAndProfile(Project $project, Profile $profile): ?ProjectParticipant
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.project = :project')
            ->andWhere('pp.profile = :profile')
            ->andWhere('pp.leftAt IS NULL')
            ->andWhere('pp.joinedAt IS NOT NULL')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->orderBy('pp.joinedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isActiveParticipant(Project $project, Profile $profile): bool
    {
        return $this->findActiveParticipantsByProjectAndProfile($project, $profile) !== null;
    }
}
