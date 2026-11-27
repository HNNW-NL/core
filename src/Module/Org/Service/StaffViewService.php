<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Security\Permission;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

final class StaffViewService
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    public function getStaff(): array
    {
        return $this->buildStaff();
    }

    private function buildStaff(?string $memberId = null): array
    {
        $empty = [
            'items' => [], 'roles' => [], 'assignableRoles' => [], 'organisations' => [],
            'organisationId' => null, 'canAdd' => false, 'canChangeRole' => false, 'canRemove' => false,
        ];
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();

        if ($request === null || !$user instanceof Account) {
            return $empty;
        }

        $organisations = [];
        $masks = [];
        foreach ($user->getOrgMemberships() as $membership) {
            $organisation = $membership->getOrganisation();
            if ($organisation === null || $organisation->getDeletedAt() !== null) {
                continue;
            }

            $id = (string) $organisation->getId();
            $organisations[$id] = $organisation;
            $masks[$id] = ($masks[$id] ?? 0) | StaffOrganisationContext::roleMask($membership->getOrgRole(), $organisation);
        }

        $organisations = array_filter($organisations, static fn ($organisation, string $id): bool => Permission::ORG_VIEW->in($masks[$id]) && Permission::ORG_MANAGE_MEMBERS->in($masks[$id]), ARRAY_FILTER_USE_BOTH);
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

        $criteria = ['organisation' => $organisation];

        if ($memberId !== null) {
            if (!\Symfony\Component\Uid\Ulid::isValid($memberId)) {
                return $empty;
            }

            $criteria['id'] = \Symfony\Component\Uid\Ulid::fromString($memberId);
        }

        $members = $this->doctrine
            ->getRepository(OrgMember::class)
            ->findBy($criteria);

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

        if ($memberId !== null) {
            return array_replace($empty, [
                'items' => $items,
                'organisationId' => $id,
                'organisationName' => (string) $organisation->getName(),
            ]);
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

    public function getStaffMember(string $id): ?array
    {
        $data = $this->buildStaff($id);
        $member = $data['items'][0] ?? null;

        if ($member === null) {
            return null;
        }

        return [
            'member' => $member,
            'organisationId' => $data['organisationId'],
            'organisationName' => $data['organisationName'],
        ];
    }
}
