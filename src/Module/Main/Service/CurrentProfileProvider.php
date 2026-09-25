<?php

namespace App\Module\Main\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CurrentProfileProvider
{
    public function __construct(
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getProfile(): ?Profile
    {
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