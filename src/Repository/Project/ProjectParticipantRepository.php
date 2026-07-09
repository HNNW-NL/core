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
        return $this->createQueryBuilder('pp')
            ->select([
                'pp.id AS participant_id',
                'IDENTITY(pp.profile) AS profile_id',
                'p.displayName AS display_name',
                'IDENTITY(pp.status) AS status_id',
                's.name AS status_name',
                'IDENTITY(pp.role) AS role_id',
                'pp.joinedAt',
                'pp.leftAt',
            ])
            ->innerJoin('pp.profile', 'p')
            ->innerJoin('pp.status', 's')
            ->innerJoin('pp.project', 'pr')
            ->leftJoin('pp.role', 'r')
            ->addSelect('r.name AS role_name')
            ->where('pr.id = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('p.displayName', 'ASC')
        return $this->createQueryBuilder('participant')
            ->andWhere('participant.project = :project')
            ->andWhere('participant.profile = :profile')
            ->andWhere('participant.leftAt IS NULL')
            ->setParameter('project', $project)
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getArrayResult();
            ->getOneOrNullResult();
    }

    public function isActiveParticipant(Project $project, Profile $profile): bool
    {
        return $this->findActiveParticipant($project, $profile) !== null;
    }
}
