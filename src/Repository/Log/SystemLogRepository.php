<?php 

namespace App\Repository\Log;

use App\Entity\Log\SystemLog;
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
}