<?php

namespace App\Repository\Account;

use App\Entity\Account\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function findLatestChanged(int $limit = 100) : array
    {
        return $this->createQueryBuilder('p')
                ->orderBy('p.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
    }

}