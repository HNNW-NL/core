<?php

namespace App\Module\Auth\Security;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

// deze provider zoekt een account op e-mail of gebruikersnaam zonder op hoofdletters te letten, zodat test@test.nl en Test@test.nl allebei werken
final class AccountProvider implements UserProviderInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    // symfony roept dit aan bij het inloggen met wat de gebruiker in het veld 'username' heeft getypt
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $account = $this->findByEmailOrUsername($identifier);

        if (!$account) {
            $exception = new UserNotFoundException(sprintf('Account "%s" niet gevonden.', $identifier));
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $account;
    }

    // ook de registratie gebruikt dit, om te kijken of een e-mail of gebruikersnaam al bestaat
    public function findByEmailOrUsername(string $identifier): ?Account
    {
        // als het goed is maakt LOWER() aan beide kanten de vergelijking hoofdletterongevoelig
        return $this->em->createQuery('SELECT a FROM App\Entity\Account\Account a WHERE LOWER(a.email) = :id OR LOWER(a.username) = :id')
            ->setParameter('id', strtolower($identifier))
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

    // bij elk volgend request haalt symfony het account opnieuw op met het id uit de sessie
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Account) {
            throw new UnsupportedUserException(sprintf('Deze provider kent "%s" niet.', $user::class));
        }

        $account = $this->em->getRepository(Account::class)->find($user->getId());

        if (!$account) {
            $exception = new UserNotFoundException('Account bestaat niet meer.');
            $exception->setUserIdentifier($user->getUserIdentifier());

            throw $exception;
        }

        return $account;
    }

    public function supportsClass(string $class): bool
    {
        return $class === Account::class || is_subclass_of($class, Account::class);
    }
}
