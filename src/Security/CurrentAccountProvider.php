<?php

namespace App\Security;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final readonly class CurrentAccountProvider
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function getAccount(): ?Account
    {
        $user = $this->security->getUser();
        if ($user !== null) {
            return $user instanceof Account ? $user : null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $accountId = $request->getSession()->get('account_id');
        if (!is_string($accountId) || !Uuid::isValid($accountId)) {
            return null;
        }

        $account = $this->entityManager->find(Account::class, Uuid::fromString($accountId));

        return $account instanceof Account && $account->getDeletedAt() === null ? $account : null;
    }
}
