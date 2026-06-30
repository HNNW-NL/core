<?php
namespace App\Repository\Project;

use App\Entity\Account\Profile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InviteParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Profile::class);
    }

    public function searchByDisplayName(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.displayName')
            ->andWhere('LOWER(p.displayName) LIKE :query')
            ->setParameter('query', mb_strtolower(trim($query)) . '%')
            ->orderBy('p.displayName', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
