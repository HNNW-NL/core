<?php

namespace App\Module\AccountCentre\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Notification;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class AccountDashboardService
{
    public const PENDING_STATUSES = ['send', 'pending', 'in behandeling'];
    private const PAGE_SIZE = 20;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return array{applications: int, projects: int, notifications: int} */
    public function totals(Account $account): array
    {
        return [
            'applications' => $this->count($this->applicationsQuery($account, true)),
            'projects' => $this->count($this->projectsQuery($account)),
            'notifications' => $this->count($this->notificationsQuery($account)),
        ];
    }

    public function applications(Account $account, bool $pendingOnly = false, int $page = 1): array
    {
        return $this->paginate($this->applicationsQuery($account, $pendingOnly), $page);
    }

    public function projects(Account $account, int $page = 1): array
    {
        return $this->paginate($this->projectsQuery($account), $page);
    }

    public function notifications(Account $account, int $page = 1): array
    {
        return $this->paginate($this->notificationsQuery($account), $page);
    }

    private function applicationsQuery(Account $account, bool $pendingOnly): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->select('record')->from(ProjectApplication::class, 'record')
            ->innerJoin('record.profile', 'profile')->innerJoin('record.project', 'project')
            ->andWhere('profile.account = :account')->setParameter('account', $account)
            ->andWhere('profile.deletedAt IS NULL')->andWhere('record.deletedAt IS NULL')->andWhere('project.deletedAt IS NULL');
        if ($pendingOnly) {
            $query->innerJoin('record.status', 'status')->andWhere('LOWER(status.name) IN (:pending)')
                ->andWhere('LOWER(status.scope) = :scope')
                ->setParameter('pending', self::PENDING_STATUSES)->setParameter('scope', 'projectapplication');
        }

        return $query;
    }

    private function projectsQuery(Account $account): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()->select('DISTINCT record')->from(Project::class, 'record')
            ->innerJoin('record.participants', 'participant')->innerJoin('participant.profile', 'profile')
            ->innerJoin('participant.status', 'participantStatus')->innerJoin('record.status', 'projectStatus')
            ->andWhere('profile.account = :account')->setParameter('account', $account)
            ->andWhere('profile.deletedAt IS NULL')->andWhere('record.deletedAt IS NULL')->andWhere('participant.leftAt IS NULL')
            ->andWhere('LOWER(participantStatus.name) = :active')->andWhere('LOWER(projectStatus.name) = :active')
            ->andWhere('LOWER(participantStatus.scope) = :participantScope')->andWhere('LOWER(projectStatus.scope) = :projectScope')
            ->setParameter('active', 'active')->setParameter('participantScope', 'projectparticipant')->setParameter('projectScope', 'project');
    }

    private function notificationsQuery(Account $account): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()->select('record')->from(Notification::class, 'record')
            ->andWhere('record.account = :account')->setParameter('account', $account)
            ->andWhere('record.deletedAt IS NULL')->andWhere('record.isRead = :unread')->setParameter('unread', false);
    }

    private function count(QueryBuilder $query): int
    {
        return (int) (clone $query)->select('COUNT(DISTINCT record.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return array{items: array, total: int, page: int, pages: int} */
    private function paginate(QueryBuilder $query, int $page): array
    {
        $total = $this->count($query);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));

        return [
            'items' => $query->orderBy('record.createdAt', 'DESC')->addOrderBy('record.id', 'DESC')
                ->setFirstResult(($page - 1) * self::PAGE_SIZE)->setMaxResults(self::PAGE_SIZE)->getQuery()->getResult(),
            'total' => $total, 'page' => $page, 'pages' => $pages,
        ];
    }
}
