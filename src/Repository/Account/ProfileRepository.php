<?php

namespace App\Repository\Account;

use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('LOWER(p.displayName) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->getQuery()
            ->getResult();
    }

    public function searchExcluding(string $query, array $excludedProfileIds): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('LOWER(p.displayName) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%');

        if (!empty($excludedProfileIds)) {
            $qb
                ->andWhere('p.id NOT IN (:excludedProfileIds)')
                ->setParameter('excludedProfileIds', $excludedProfileIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p')
            ->innerJoin(ProjectParticipant::class, 'pp', 'WITH', 'pp.profile = p')
            ->andWhere('pp.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getResult();
    }

}
