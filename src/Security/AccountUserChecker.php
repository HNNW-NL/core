<?php

namespace App\Security;

use App\Entity\Account\Account;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Controleert of een account mag inloggen (AU02-C).
 * - Verwijderde (soft-deleted) accounts mogen niet inloggen.
 * - Alleen accounts met status 'active' mogen inloggen; elke andere status
 *   (bijv. geblokkeerd) wordt geweigerd.
 */
final class AccountUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        // Bewust leeg: we checken pas NA het wachtwoord, zodat iemand zonder
        // geldig wachtwoord niet kan achterhalen of een account geblokkeerd is.
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof Account) {
            return;
        }

        if (null !== $user->getDeletedAt()) {
            throw new CustomUserMessageAccountStatusException('Ongeldige gebruikersnaam/e-mail of wachtwoord.');
        }

        if ('active' !== $user->getStatus()?->getName()) {
            throw new CustomUserMessageAccountStatusException('Je account is geblokkeerd. Neem contact op met de beheerder.');
        }
    }
}