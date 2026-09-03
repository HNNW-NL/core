<?php

namespace App\Repository\Common;

use App\Entity\Common\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
/**
 * @extends ServiceEntityRepository<Status>
 */
class StatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Status::class);
    }

    public function findOneByScopeAndName(string $scope, string $name): ?Status
    {
        return $this->createQueryBuilder('status')
            ->andWhere('LOWER(status.scope) = LOWER(:scope)')
            ->andWhere('LOWER(status.name) = LOWER(:name)')
            ->setParameter('scope', $scope)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<string> $names
     */
    public function findFirstByScopeAndNames(string $scope, array $names): ?Status
    {
        foreach ($names as $name) {
            $status = $this->findOneByScopeAndName($scope, $name);
            if ($status !== null) {
                return $status;
            }
        }

        return null;
    }
}