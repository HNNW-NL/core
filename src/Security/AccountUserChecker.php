<?php

namespace App\Security;

use App\Entity\Account\Account;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class AccountUserChecker implements UserCheckerInterface
{
    public function isEnabled(Account $account): bool
    {
        $status = $account->getStatus();

        return $account->getDeletedAt() === null
            && $status !== null
            && strcasecmp((string) $status->getScope(), 'account') === 0
            && strcasecmp((string) $status->getName(), 'active') === 0;
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Account || !$this->isEnabled($user)) {
            throw new DisabledException('Account is niet beschikbaar.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
