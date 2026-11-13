<?php

namespace App\Module\Admin\Service;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class AccountOverview
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorization,
        private RequestStack $requests,
    ) {
    }

    public function load(): array
    {
        if (!$this->authorization->isGranted('ROLE_ADMIN') || !$this->authorization->isGranted('ADMIN_MANAGE_ACCOUNTS')) {
            throw new AccessDeniedException();
        }

        $query = $this->requests->getCurrentRequest()?->query->all() ?? [];
        $searchValue = $query['q'] ?? $query['search'] ?? null;
        $search = is_string($searchValue) ? mb_substr(trim($searchValue), 0, 200) : '';
        $requestedStatus = is_string($query['status'] ?? null) ? $query['status'] : '';
        $requestedPage = is_string($query['page'] ?? null) ? filter_var($query['page'], FILTER_VALIDATE_INT) : false;

        $statuses = $this->entityManager->getRepository(Status::class)->findBy(['scope' => 'account'], ['name' => 'ASC', 'id' => 'ASC']);
        $statusFilter = null;
        foreach ($statuses as $status) {
            if ((string) $status->getId() === $requestedStatus) {
                $statusFilter = $status;
                break;
            }
        }

        $accounts = $this->entityManager->createQueryBuilder()
            ->from(Account::class, 'account')
            ->leftJoin('account.profile', 'profile')
            ->andWhere('account.deletedAt IS NULL');

        if ($search !== '') {
            $accounts->andWhere(
                "LOWER(account.email) LIKE :search ESCAPE '!' OR LOWER(account.username) LIKE :search ESCAPE '!'"
                ." OR LOWER(profile.firstName) LIKE :search ESCAPE '!' OR LOWER(profile.lastName) LIKE :search ESCAPE '!'"
                ." OR LOWER(profile.displayName) LIKE :search ESCAPE '!'",
            )->setParameter('search', '%'.strtr(mb_strtolower($search), ['!' => '!!', '%' => '!%', '_' => '!_']).'%');
        }
        if ($statusFilter !== null) {
            $accounts->andWhere('account.status = :status')->setParameter('status', $statusFilter);
        }

        $total = (int) (clone $accounts)->select('COUNT(account.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, intdiv($total, self::PAGE_SIZE) + (int) ($total % self::PAGE_SIZE !== 0));
        $page = min($pages, max(1, $requestedPage ?: 1));
        $items = $accounts->select('account.username, account.email, profile.firstName, profile.lastName, status.name AS statusName')
            ->leftJoin('account.status', 'status')
            ->orderBy('account.username', 'ASC')
            ->addOrderBy('account.id', 'ASC')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()->getArrayResult();

        return [
            'items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'search' => $search, 'status' => (string) $statusFilter?->getId(), 'statuses' => $statuses,
        ];
    }
}
