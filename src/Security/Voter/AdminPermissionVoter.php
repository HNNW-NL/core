<?php

namespace App\Security\Voter;

use App\Entity\Account\Account;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Beoordeelt permissions in de "Admin"-scope (ADMIN_*), dus platformbrede
 * rechten die vastliggen op Account -> Admin -> AdminRole::permissionsMask.
 *
 * Gebruik: $this->denyAccessUnlessGranted('ADMIN_MANAGE_SETTINGS');
 * of via het attribute: #[IsGranted('ADMIN_MANAGE_SETTINGS')]
 */
final class AdminPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        $permission = Permission::fromName($attribute);

        return $permission !== null && str_starts_with($attribute, 'ADMIN_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $account = $token->getUser();

        if (!$account instanceof Account) {
            return false;
        }

        $admin = $account->getAdmin();

        if ($admin === null) {
            return false;
        }

        // Een super admin mag altijd alles, los van de mask.
        if ($admin->isSuperAdmin()) {
            return true;
        }

        $permission = Permission::fromName($attribute);
        if ($permission === null) {
            return false;
        }

        return $permission->in($admin->getAdminRole()->getPermissionsMask());
    }
}