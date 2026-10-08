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

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('auditLog')
            ->orderBy('auditLog.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByCursor(?AuditLog $cursor = null,?Account $actor = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null,bool $isForward = true, int $limit = 20) : array
    {
        $qb =  $this->createQueryBuilder('auditLog');
        $query =   $qb->orderBy('auditLog.id', ($isForward)?'ASC':'DESC')
        ->addOrderBy('auditLog.createdAt',($isForward)?'ASC':'DESC')
            ->setMaxResults($limit);

        if($cursor != null)
        {
            $compare = ($isForward)? ' > ': ' < ' ;
            $query->andWhere('auditLog.id'.$compare .':cursorID')
            ->andWhere('auditLog.createdAt'.$compare .'= :cursorCreatedAt')
            ->setParameter('cursorID', $cursor->getId())
            ->setParameter('cursorCreatedAt', $cursor->getCreatedAt());
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
                    $sort = $a->getCreatedAt() <=> $b->getCreatedAt();                     
                    return ($sort  != 0)? $sort  : strcmp($a->getId(), $b->getId());
                });
            return  $query;
        }

        return $query->getQuery()
        ->getResult();
    }

    public function hasNext(AuditLog $cursor,?Account $actor = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null) : Bool
    {
        $result = $this->findByCursor($cursor,$actor,$startPeriod,$endPeriod,true,1);
        return count($result) > 0;
    }

    public function hasPrev(AuditLog $cursor,?Account $actor = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null) : Bool
    {
        $result = $this->findByCursor($cursor,$actor,$startPeriod,$endPeriod,false,1);
        return count($result)> 0;
    }
}
