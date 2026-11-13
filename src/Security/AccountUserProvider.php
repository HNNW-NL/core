<?php

namespace App\Security;

use App\Entity\Account\Account;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final readonly class AccountUserProvider implements UserProviderInterface
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $repository = $this->doctrine->getRepository(Account::class);
        $account = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $repository->findOneBy(['email' => $identifier])
            : $repository->findOneBy(['username' => $identifier]);

        if (!$account instanceof Account) {
            $exception = new UserNotFoundException('Account not found.');
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $account;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Account) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, Account::class, true);
    }
}
