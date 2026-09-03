<?php
namespace App\Repository\Project;

use App\Entity\Project\ProjectRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProjectRoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectRole::class);
    }

    public function findByProjectIdRoles(string $projectId): array
    {
        return $this->createQueryBuilder('r')
            ->select('r')
            ->where('IDENTITY(r.project) = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
