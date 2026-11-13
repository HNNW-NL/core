<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CurrentProfileProvider
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getProfile(): ?Profile
    {
        // Symfony Security login
        $user = $this->security->getUser();

        if ($user instanceof Account) {
            return $user->getProfile();
        }

        // Fallback for the current session-based login
        $session = $this->requestStack->getSession();
        $accountId = $session->get('account_id');

        if (!$accountId) {
            return null;
        }

        $account = $this->entityManager
            ->getRepository(Account::class)
            ->find($accountId);

        if (!$account instanceof Account) {
            return null;
        }

        return $account->getProfile();
    }
}