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

    public function findByTitleAndReceiver(?string $title = null, ?string $receiver = null ,int $limit = 10 ) : array
    {
        $query = $this->createQueryBuilder('n')
                ->Where('n.deletedAt IS NULL')
                ->orderBy('n.createdAt', 'DESC')
                ->setMaxResults($limit);

        if($title != null |  $title != '')
        {
            $query->andWhere("LOWER(n.title) LIKE LOWER(:title)")
            ->setParameter('title', '%' . strtolower($title) . '%');
        }

        if($receiver != null |  $receiver != '')
        {
            $query->innerJoin('n.account', 'account')
            ->andWhere('LOWER(account.username) LIKE LOWER(:receiver)')
            ->orWhere('LOWER(account.email) LIKE LOWER(:receiver)')
            ->setParameter('receiver', '%' . strtolower($receiver) . '%');
        }

        return   $query->getQuery()->getResult();
    }

}