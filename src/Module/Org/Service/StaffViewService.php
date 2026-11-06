<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Security\Permission;
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
        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $accountId = $session?->get('account_id');
        $empty = [
            'items' => [], 'roles' => [], 'assignableRoles' => [], 'organisations' => [],
            'organisationId' => null, 'canAdd' => false, 'canChangeRole' => false, 'canRemove' => false,
        ];
        $request = $this->requestStack->getCurrentRequest();
        $accountId = $request?->hasPreviousSession() ? $request->getSession()->get('account_id') : null;

        if (!$accountId) {
            return ['items' => [], 'roles' => []];
        if (!is_string($accountId) || !Uuid::isValid($accountId)) {
            return $empty;
        }

        $user = $this->doctrine
            ->getRepository(Account::class)
            ->find($accountId);
            ->find(Uuid::fromString($accountId));

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
        $organisations = [];
        $masks = [];
        foreach ($user->getOrgMemberships() as $membership) {
            $organisation = $membership->getOrganisation();
            if ($organisation === null) {
                continue;
            }

            $id = (string) $organisation->getId();
            $organisations[$id] = $organisation;
            $masks[$id] = ($masks[$id] ?? 0) | StaffOrganisationContext::roleMask($membership->getOrgRole(), $organisation);
        }

        $organisations = array_filter($organisations, static fn ($organisation, string $id): bool => Permission::ORG_VIEW->in($masks[$id]), ARRAY_FILTER_USE_BOTH);
        $empty['organisations'] = array_map(static fn ($organisation): string => (string) $organisation->getName(), $organisations);
        $session = $request->getSession();
        $id = $request->query->has(StaffOrganisationContext::QUERY_KEY)
            ? StaffOrganisationContext::requestedId($request)
            : $session->get(StaffOrganisationContext::SESSION_KEY);
        if (!$request->query->has(StaffOrganisationContext::QUERY_KEY)
            && (!is_string($id) || !isset($organisations[$id]))
            && count($organisations) === 1
        ) {
            $id = array_key_first($organisations);
        }
        if (!is_string($id) || !isset($organisations[$id])) {
            return $empty;
        }

        $organisation = $organisations[$id];
        $session->set(StaffOrganisationContext::SESSION_KEY, $id);
        $canManageMembers = Permission::ORG_MANAGE_MEMBERS->in($masks[$id]);
        $canManageRoles = Permission::ORG_MANAGE_ROLES->in($masks[$id]);

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
        $assignableRoles = [];

        foreach ($orgRoles as $orgRole) {
            $role = [
                'id' => (string) $orgRole->getId(),
                'name' => $orgRole->getName(),
            ];
            $roles[] = $role;
            if ((StaffOrganisationContext::roleMask($orgRole, $organisation) & ~$masks[$id]) === 0) {
                $assignableRoles[] = $role;
            }
        }

        return array_replace($empty, [
            'items' => $items,
            'roles' => $roles,
            'assignableRoles' => $assignableRoles,
            'organisationId' => $id,
            'canAdd' => $canManageMembers && $canManageRoles,
            'canChangeRole' => $canManageRoles,
            'canRemove' => $canManageMembers,
        ]);
    }
}
