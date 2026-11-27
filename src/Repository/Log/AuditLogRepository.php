<?php

namespace App\Repository\Log;

use App\Entity\Log\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('auditLog')
            ->orderBy('auditLog.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<string> $taskIds
     * @return array<string, AuditLog>
     */
    public function findLatestPackageTaskWorkflowEvents(array $taskIds): array
    {
        if ($taskIds === []) {
            return [];
        }

        $events = $this->createQueryBuilder('auditLog')
            ->andWhere('auditLog.entityType = :entityType')
            ->andWhere('auditLog.entityId IN (:taskIds)')
            ->andWhere('auditLog.action IN (:actions)')
            ->setParameter('entityType', \App\Entity\Project\PackageTask::class)
            ->setParameter('taskIds', $taskIds)
            ->setParameter('actions', [
                'package_task.assigned',
                'package_task.started',
                'package_task.submitted',
                'package_task.accepted',
                'package_task.returned',
            ])
            ->orderBy('auditLog.id', 'DESC')
            ->getQuery()
            ->getResult();

        $latestByTask = [];
        foreach ($events as $event) {
            $taskId = $event->getEntityId();
            if ($taskId !== null && !isset($latestByTask[$taskId])) {
                $latestByTask[$taskId] = $event;
            }
        }

        return $latestByTask;
    }
}
