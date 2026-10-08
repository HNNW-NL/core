<?php 

namespace App\Repository\Log;

use App\Entity\Log\SystemLog;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemLog::class);
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );
    } 
    
    public function findByCursor(?SystemLog $cursor = null,?string $level = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null,bool $isForward = true, int $limit = 20) : array
    {
        $qb =  $this->createQueryBuilder('systemlog');
        $query =   $qb->orderBy('systemlog.id', ($isForward)?'ASC':'DESC')
        ->addOrderBy('systemlog.createdAt',($isForward)?'ASC':'DESC')
            ->setMaxResults($limit);

        if($cursor != null)
        {
            $compare = ($isForward)? ' > ': ' < ' ;
            $query->andWhere('systemlog.id'.$compare .':cursorID')
            ->andWhere('systemlog.createdAt'.$compare .'= :cursorCreatedAt')
            ->setParameter('cursorID', $cursor->getId())
            ->setParameter('cursorCreatedAt', $cursor->getCreatedAt());
        }

        if($level != null && $level != '')
        {
            $query->andWhere('systemlog.level = :level')
            ->setParameter('level', $level);
        }

        if($startPeriod != null)
        {
            $query->andWhere('systemlog.createdAt >= :startPeriod')
            ->setParameter('startPeriod', $startPeriod);
        }

        if($endPeriod != null)
        {
            $query->andWhere('systemlog.createdAt <= :endPeriod')
            ->setParameter('endPeriod', $endPeriod);
        }
        
        if(!$isForward)
        {
            // reorder systemlog to be ascending
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

    public function hasNext(SystemLog $cursor,?string $level = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null) : Bool
    {
        $result = $this->findByCursor($cursor,$level,$startPeriod,$endPeriod,true,1);
        return count($result) > 0;
    }

    public function hasPrev(SystemLog $cursor,?string $level = null,?DateTimeImmutable $startPeriod = null , ?DateTimeImmutable $endPeriod = null) : Bool
    {
        $result = $this->findByCursor($cursor,$level,$startPeriod,$endPeriod,false,1);
        return count($result)> 0;
    }
}