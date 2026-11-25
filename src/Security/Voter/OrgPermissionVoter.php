<?php

namespace App\Security\Voter;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Beoordeelt permissions in de "Organisation"-scope (ORG_*). Dit zijn
 * rechten die per organisatie kunnen verschillen: iemand kan bij
 * Organisatie A "Organisation Admin" zijn (alle ORG_* rechten) en bij
 * Organisatie B gewoon "User" (alleen ORG_VIEW).
 *
 * Het subject van de check moet altijd een Organisation zijn:
 * $this->denyAccessUnlessGranted('ORG_MANAGE_MEMBERS', $organisation);
 */
final class OrgPermissionVoter extends Voter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'ORG_')
            && $subject instanceof Organisation
            && Permission::fromName($attribute) !== null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $account = $token->getUser();

        if (!$account instanceof Account || !$subject instanceof Organisation) {
            return false;
        }

        // Platform-admins mogen altijd bij elke organisatie.
        if ($account->getAdmin()?->isSuperAdmin()) {
            return true;
        }

        $permission = Permission::fromName($attribute);

        if ($permission === null) {
            return false;
        }

        $memberships = $this->entityManager
            ->getRepository(OrgMember::class)
            ->findBy([
                'organisation' => $subject,
                'account' => $account,
            ]);

        foreach ($memberships as $membership) {
            $role = $membership->getOrgRole();

            if (
                $role !== null &&
                $role->getOrganisation()?->getId()?->equals($subject->getId()) &&
                $permission->in($role->getPermissionsMask())
            ) {
                return true;
            }
        }

        return false;
    }
}
