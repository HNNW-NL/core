<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

final class StaffViewService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private Security $security,
    ) {
    }

    public function getStaff(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof Account) {
            return ['items' => []];
        }

        $memberships = $user->getOrgMemberships();

        if ($memberships->isEmpty()) {
            return ['items' => []];
        }

        /** @var OrgMember $membership */
        $membership = $memberships->first();
        $organisation = $membership->getOrganisation();

        if ($organisation === null) {
            return ['items' => []];
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

        return ['items' => $items];
    }
}