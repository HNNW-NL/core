<?php

namespace App\Module\Admin\Service;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Attribute\AsTwigFunction;

final readonly class DashboardTotals
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /** @return array{accounts: int, organisations: int, projects: int} */
    #[AsTwigFunction('admin_dashboard_totals')]
    public function count(): array
    {
        if (!$this->authorizationChecker->isGranted('ROLE_ADMIN')
            || !$this->authorizationChecker->isGranted('ADMIN_VIEW_DASHBOARD')
        ) {
            throw new AccessDeniedException();
        }

        $active = ['deletedAt' => null];

        return [
            'accounts' => $this->entityManager->getRepository(Account::class)->count($active),
            'organisations' => $this->entityManager->getRepository(Organisation::class)->count($active),
            'projects' => $this->entityManager->getRepository(Project::class)->count($active),
        ];
    }
}
