<?php

namespace App\Module\AccountCentre\Service;

use Symfony\Bridge\Doctrine\Types\UlidType;
use App\Entity\Account\{Account, Notification, Profile};
use App\Entity\Project\ProjectApplication;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Repository\Project\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final class AccountDashboardService
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly ProjectRepository $projects)
    {
    }

    public function profile(Account $account): ?Profile
    {
        return $this->entityManager->getRepository(Profile::class)->findOneBy(['account' => $account, 'deletedAt' => null]);
    }

    public function totals(Account $account): array
    {
        $profile = $this->profile($account);

        return [
            'applications' => $this->count($this->applicationsQuery($account, true)),
            'projects' => $profile === null ? 0 : $this->projects->countByProfileOnQuery('', $profile, true),
            'notifications' => $this->count($this->notificationsQuery($account, true)),
        ];
    }

    public function applications(Account $account, bool $pendingOnly = false, int $page = 1): array
    {
        return $this->paginate($this->applicationsQuery($account, $pendingOnly), $page);
    }

    public function notifications(Account $account, bool $unreadOnly = false, int $page = 1): array
    {
        return $this->paginate($this->notificationsQuery($account, $unreadOnly), $page);
    }

    private function applicationsQuery(Account $account, bool $pendingOnly): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->select('record')->from(ProjectApplication::class, 'record')
            ->innerJoin('record.profile', 'profile')->innerJoin('record.project', 'project')
            ->where('profile.account = :account')->setParameter('account', $account->getId(), UlidType::NAME)
            ->andWhere('profile.deletedAt IS NULL')->andWhere('record.deletedAt IS NULL')->andWhere('project.deletedAt IS NULL');
        if ($pendingOnly) {
            $query->innerJoin('record.status', 'status')
                ->andWhere('LOWER(status.scope) = :scope')->setParameter('scope', 'projectapplication')
                ->andWhere('LOWER(status.name) IN (:pending)')->setParameter('pending', ProjectParticipantUpdateRoleHandler::PENDING_NAMES);
        }

        return $query;
    }

    private function notificationsQuery(Account $account, bool $unreadOnly): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()->select('record')->from(Notification::class, 'record')
            ->where('record.account = :account')->setParameter('account', $account->getId(), UlidType::NAME)
            ->andWhere('record.deletedAt IS NULL')->andWhere('(record.expiresAt IS NULL OR record.expiresAt > :now)')
            ->setParameter('now', new \DateTimeImmutable());
        if ($unreadOnly) {
            $query->andWhere('record.isRead = :unread')->setParameter('unread', false);
        }

        return $query;
    }

    private function count(QueryBuilder $query): int
    {
        return (int) (clone $query)->select('COUNT(DISTINCT record.id)')->getQuery()->getSingleScalarResult();
    }

    private function paginate(QueryBuilder $query, int $page): array
    {
        $total = $this->count($query);
        $pages = max(1, (int) ceil($total / 20));
        $page = max(1, min($page, $pages));

        return [
            'items' => $query->orderBy('record.id', 'DESC')->setMaxResults(20)->setFirstResult(($page - 1) * 20)->getQuery()->getResult(),
            'total' => $total, 'page' => $page, 'pages' => $pages,
        ];
    }
}
