<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

final class StaffViewService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private RequestStack $requestStack,
    ) {
    }

    public function getStaff(): array
    {
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $accountId = $session?->get('account_id');

        if (!$accountId) {
            return ['items' => [], 'roles' => []];
        }

        $user = $this->doctrine
            ->getRepository(Account::class)
            ->find($accountId);

        if (!$user instanceof Account) {
            return ['items' => [], 'roles' => []];
        }

        $memberships = $user->getOrgMemberships();

        if ($memberships->isEmpty()) {
            return ['items' => [], 'roles' => []];
        }

        /** @var OrgMember $membership */
        $membership = $memberships->first();
        $organisation = $membership->getOrganisation();

        if ($organisation === null) {
            return ['items' => [], 'roles' => []];
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
        ];
    }
}
