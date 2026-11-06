<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class StaffViewService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private RequestStack $requestStack,
    ) {
    }

    public function getStaff(): array
    {
        $empty = ['items' => [], 'roles' => [], 'canManage' => false];
        $request = $this->requestStack->getCurrentRequest();
        $accountId = $request?->hasPreviousSession() ? $request->getSession()->get('account_id') : null;

        if (!is_string($accountId) || !Uuid::isValid($accountId)) {
            return $empty;
        }

        $user = $this->doctrine
            ->getRepository(Account::class)
            ->find(Uuid::fromString($accountId));

        if (!$user instanceof Account) {
            return $empty;
        }

        $memberships = $user->getOrgMemberships();

        if ($memberships->isEmpty()) {
            return $empty;
        }

        /** @var OrgMember $membership */
        $membership = $memberships->first();
        $organisation = $membership->getOrganisation();

        if ($organisation === null) {
            return $empty;
        }

        $canManage = false;
        foreach ($memberships as $membership) {
            if ((string) $membership->getOrganisation()?->getId() !== (string) $organisation->getId()) {
                return $empty + ['needsOrganisation' => true];
            }

            $canManage = $canManage || StaffManagementService::isBeheerder($membership->getOrgRole(), $organisation);
        }

        $members = $this->doctrine
            ->getRepository(OrgMember::class)
            ->findBy(['organisation' => $organisation]);

        $items = [];

        foreach ($members as $member) {
            $account = $member->getAccount();
            $profile = $account?->getProfile();
            $role = $member->getOrgRole();

            if ($account === null) {
                continue;
            }

            $items[] = [
                'id' => (string) $member->getId(),
                'firstName' => $profile?->getFirstName(),
                'lastName' => $profile?->getLastName(),
                'displayName' => $profile?->getDisplayName(),
                'email' => $account->getEmail(),
                'role' => $role?->getName(),
                'roleId' => (string) $role?->getId(),
            ];
        }

        $orgRoles = $this->doctrine
            ->getRepository(OrgRole::class)
            ->findBy(['organisation' => $organisation]);

        $roles = [];

        foreach ($orgRoles as $orgRole) {
            $roles[] = [
                'id' => (string) $orgRole->getId(),
                'name' => $orgRole->getName(),
            ];
        }

        return [
            'items' => $items,
            'roles' => $roles,
            'canManage' => $canManage,
        ];
    }
}
