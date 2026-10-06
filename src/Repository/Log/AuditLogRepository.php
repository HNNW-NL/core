<?php

namespace App\Repository\Log;

use App\Entity\Account\Account;
use App\Entity\Log\AuditLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\Uid\Uuid;

class AuditLogRepository extends ServiceEntityRepository
{

    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $em)
    {
        parent::__construct($registry, AuditLog::class);
        $this->em = $em;
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('auditLog')
            ->orderBy('auditLog.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAuditLogByCursor(?Uuid $cursor = null,?Account $actor = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null,bool $isForward = true, int $limit = 5) : array
    {
        $qb =  $this->createQueryBuilder('auditLog');
        $query =   $qb->orderBy('auditLog.id', ($isForward)?'ASC':'DESC')
            ->setMaxResults($limit);

        if($cursor != null)
        {
            $compare = ($isForward)? '>': '<' ;
            $query ->andWhere('auditLog.id'.$compare .':cursor')
            ->setParameter('cursor', $cursor);
        }

        if($actor != null)
        {
            $query->join('auditLog.actorAccount','actor')
            ->andWhere('actor.id = :actorId')
            ->setParameter('actorId', $actor->getId());
        }

        if($startPeriod != null)
        {
            $query->andWhere('auditLog.createdAt >= :startPeriod')
            ->setParameter('startPeriod', $startPeriod);
        }

        if($endPeriod != null)
        {
            $query->andWhere('auditLog.createdAt <= :endPeriod')
            ->setParameter('endPeriod', $endPeriod);
        }
        
        if(!$isForward)
        {
            // reorder auditlogs to be ascending
            $query = $query->getQuery()
            ->getResult();    
                usort($query, function($a, $b) {
                    return strcmp($a->getId(), $b->getId());
                });
            return  $query;
        }

        return $query->getQuery()
        ->getResult();
    }

    public function hasNext(Uuid $cursor,?Account $actor = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null) : Bool
    {
        $qb =  $this->createQueryBuilder('auditLog')
            ->select('Count(auditLog.id)')
            ->andWhere('auditLog.id > :cursor')
            ->setParameter('cursor', $cursor);        

        if($actor != null)
        {
            $qb->join('auditLog.actorAccount','actor')
            ->andWhere('actor.id = :actorId')
            ->setParameter('actorId', $actor->getId());
        }

        if($startPeriod != null)
        {
            $qb->andWhere('auditLog.createdAt >= :startPeriod')
            ->setParameter('startPeriod', $startPeriod);
        }

        if($endPeriod != null)
        {
            $qb->andWhere('auditLog.createdAt <= :endPeriod')
            ->setParameter('endPeriod', $endPeriod);
        }
        
        return $qb->getQuery()
        ->getSingleScalarResult() != 0;
    }


    public function hasPrev(Uuid $cursor,?Account $actor = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null) : Bool
    {
        $qb =  $this->createQueryBuilder('auditLog')
            ->select('Count(auditLog.id)')
            ->andWhere('auditLog.id < :cursor')
            ->setParameter('cursor', $cursor);        

        if($actor != null)
        {
            $qb->join('auditLog.actorAccount','actor')
            ->andWhere('actor.id = :actorId')
            ->setParameter('actorId', $actor->getId());
        }

        if($startPeriod != null)
        {
            $qb->andWhere('auditLog.createdAt >= :startPeriod')
            ->setParameter('startPeriod', $startPeriod);
        }

        if($endPeriod != null)
        {
            $qb->andWhere('auditLog.createdAt <= :endPeriod')
            ->setParameter('endPeriod', $endPeriod);
        }
        
        return $qb->getQuery()
        ->getSingleScalarResult() != 0;
    }
}
