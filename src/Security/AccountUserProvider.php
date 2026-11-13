<?php

namespace App\Security;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * Laadt een Account voor Symfony Security. Ondersteunt inloggen op zowel
 * username als e-mailadres, net zoals de oude handmatige login-logica deed.
 */
final class AccountUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $repository = $this->entityManager->getRepository(Account::class);

        $account = $repository->findOneBy(['username' => $identifier])
            ?? $repository->findOneBy(['email' => $identifier]);

        if (!$account instanceof Account) {
            throw new UserNotFoundException(sprintf('Geen account gevonden voor "%s".', $identifier));
        }

        return $account;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Account) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $refreshed = $this->entityManager->getRepository(Account::class)->find($user->getId());

        if (!$refreshed instanceof Account) {
            throw new UserNotFoundException('Account bestaat niet meer.');
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return Account::class === $class || is_subclass_of($class, Account::class);
    }

        public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Account) {
            return;
        }

        $user->setPasswordHash($newHashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}