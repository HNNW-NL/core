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

    public function findActiveParticipant(Project $project, Profile $profile): \Doctrine\ORM\QueryBuilder
    {

    }

    public function isActiveParticipant(Project $project, Profile $profile): bool
    {
        return $this->findActiveParticipant($project, $profile) !== null;
    }
}
