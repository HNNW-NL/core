<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Org\Organisation;
use App\Security\Permission;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;

final class StaffManagementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function addMember(string $email, string $roleId): OrgMember
    {
        return $this->manage(
            [Permission::ORG_MANAGE_MEMBERS, Permission::ORG_MANAGE_ROLES],
            fn (Organisation $organisation, int $mask): OrgMember => $this->add($organisation, $email, $roleId, $mask),
        );
    }

    private function add(Organisation $organisation, string $email, string $roleId, int $mask): OrgMember
    {
        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                'Voer een geldig e-mailadres in.'
            );
        }

        $account = $this->entityManager
            ->getRepository(Account::class)
            ->findOneBy(['email' => $email]);

        if (!$account instanceof Account) {
            throw new \InvalidArgumentException(
                'Er bestaat geen account met dit e-mailadres.'
            );
        }

        $role = $this->findRoleForOrganisation($roleId, $organisation);
        $this->requireAssignableRole($role, $organisation, $mask);

        $existing = $this->entityManager
            ->getRepository(OrgMember::class)
            ->findOneBy([
                'organisation' => $organisation,
                'account' => $account,
            ]);

        if ($existing instanceof OrgMember) {
            throw new \InvalidArgumentException(
                'Dit account is al lid van de organisatie.'
            );
        }

        $member = new OrgMember();
        $member->setOrganisation($organisation);
        $member->setAccount($account);
        $member->setOrgRole($role);

        $this->entityManager->persist($member);

        return $member;
    }

    public function changeRole(string $memberId, string $roleId): OrgMember
    {
        return $this->manage(
            [Permission::ORG_MANAGE_ROLES],
            fn (Organisation $organisation, int $mask): OrgMember => $this->updateRole($organisation, $memberId, $roleId, $mask),
        );
    }

    private function updateRole(Organisation $organisation, string $memberId, string $roleId, int $mask): OrgMember
    {
        $member = $this->findMemberForOrganisation(
            $memberId,
            $organisation
        );

        $role = $this->findRoleForOrganisation(
            $roleId,
            $organisation
        );

        if ((string) $member->getOrgRole()?->getId() === (string) $role->getId()) {
            return $member;
        }

        $this->requireAssignableRole($role, $organisation, $mask);

        $existing = $this->entityManager->getRepository(OrgMember::class)->findOneBy([
            'organisation' => $organisation,
            'account' => $member->getAccount(),
            'orgRole' => $role,
        ]);

        if ($existing instanceof OrgMember) {
            throw new \InvalidArgumentException(
                'Dit account heeft deze rol al binnen de organisatie.'
            );
        }

        $this->requireManagementContinuity($organisation, $member, $role);
        $member->setOrgRole($role);

        return $member;
    }

    public function removeMember(string $memberId): void
    {
        $this->manage(
            [Permission::ORG_MANAGE_MEMBERS],
            fn (Organisation $organisation) => $this->remove($organisation, $memberId),
        );
    }

    private function remove(Organisation $organisation, string $memberId): void
    {
        $member = $this->findMemberForOrganisation(
            $memberId,
            $organisation
        );

        $this->requireManagementContinuity($organisation, $member, null);
        $this->entityManager->remove($member);
    }

    /** @param list<Permission> $requiredPermissions */
    private function manage(array $requiredPermissions, callable $operation): mixed
    {
        $account = $this->getCurrentAccount();
        $request = $this->requestStack->getCurrentRequest();
        $organisationId = StaffOrganisationContext::requestedId($request);
        if ($organisationId === null) {
            throw new \RuntimeException('Kies eerst een geldige organisatie.');
        }

        try {
            return $this->entityManager->wrapInTransaction(function () use ($account, $request, $organisationId, $requiredPermissions, $operation): mixed {
                $organisation = $this->entityManager->find(Organisation::class, Uuid::fromString($organisationId), LockMode::PESSIMISTIC_WRITE);
                if (!$organisation instanceof Organisation) {
                    throw new \RuntimeException('Geen toegang tot deze organisatie.');
                }

                $mask = $this->getAccountMask($account, $organisation);
                $requiredMask = Permission::maskFor([Permission::ORG_VIEW, ...$requiredPermissions]);
                if (($mask & $requiredMask) !== $requiredMask) {
                    throw new \RuntimeException('Je hebt onvoldoende rechten voor deze actie.');
                }

                $request->getSession()->set(StaffOrganisationContext::SESSION_KEY, $organisationId);

                return $operation($organisation, $mask);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new \InvalidArgumentException('Dit account heeft deze rol al binnen de organisatie.', 0, $e);
        }
    }

    private function requireAssignableRole(OrgRole $role, Organisation $organisation, int $actorMask): void
    {
        if ((StaffOrganisationContext::roleMask($role, $organisation) & ~$actorMask) !== 0) {
            throw new \InvalidArgumentException('Je kunt geen rol toewijzen met rechten die je zelf niet hebt.');
        }
    }

    private function requireManagementContinuity(Organisation $organisation, OrgMember $member, ?OrgRole $replacement): void
    {
        $managementMask = Permission::maskFor([Permission::ORG_VIEW, Permission::ORG_MANAGE_MEMBERS, Permission::ORG_MANAGE_ROLES]);
        $previous = StaffOrganisationContext::roleMask($member->getOrgRole(), $organisation);
        $next = StaffOrganisationContext::roleMask($replacement, $organisation);
        if (($previous & $managementMask & ~$next) === 0) {
            return;
        }

        $before = [];
        $after = [];
        $memberships = $this->entityManager->getRepository(OrgMember::class)->findBy(['organisation' => $organisation]);
        foreach ($memberships as $membership) {
            $accountId = (string) $membership->getAccount()->getId();
            $mask = StaffOrganisationContext::roleMask($membership->getOrgRole(), $organisation);
            $before[$accountId] = ($before[$accountId] ?? 0) | $mask;
            $after[$accountId] = ($after[$accountId] ?? 0) | ($membership === $member ? $next : $mask);
        }

        $requiredMasks = [
            Permission::ORG_VIEW->value | Permission::ORG_MANAGE_MEMBERS->value,
            Permission::ORG_VIEW->value | Permission::ORG_MANAGE_ROLES->value,
            $managementMask,
        ];
        foreach ($requiredMasks as $required) {
            $hasPermission = static fn (int $mask): bool => ($mask & $required) === $required;
            if (array_any($before, $hasPermission) && !array_any($after, $hasPermission)) {
                throw new \InvalidArgumentException('De organisatie moet minstens één bevoegd account voor ledenbeheer en rollenbeheer behouden.');
            }
        }
    }

    private function getAccountMask(Account $account, Organisation $organisation): int
    {
        $memberships = $this->entityManager
            ->getRepository(OrgMember::class)
            ->findBy([
                'organisation' => $organisation,
                'account' => $account,
            ]);

        $mask = 0;
        foreach ($memberships as $membership) {
            $mask |= StaffOrganisationContext::roleMask($membership->getOrgRole(), $organisation);
        }

        return $mask;
    }

    private function getCurrentAccount(): Account
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null || !$request->hasPreviousSession()) {
            throw new \RuntimeException(
                'Je moet ingelogd zijn om staff te beheren.'
            );
        }

        $accountId = $request->getSession()->get('account_id');

        if (!is_string($accountId) || !Uuid::isValid($accountId)) {
            throw new \RuntimeException(
                'Je moet ingelogd zijn om staff te beheren.'
            );
        }

        $account = $this->entityManager->find(
            Account::class,
            Uuid::fromString($accountId)
        );

        if (!$account instanceof Account) {
            throw new \RuntimeException(
                'Het ingelogde account bestaat niet meer.'
            );
        }

        return $account;
    }

    private function findRoleForOrganisation(
        string $roleId,
        Organisation $organisation
    ): OrgRole {
        if (!Uuid::isValid($roleId)) {
            throw new \InvalidArgumentException(
                'Ongeldige rol-ID.'
            );
        }

        $role = $this->entityManager->find(
            OrgRole::class,
            Uuid::fromString($roleId)
        );

        if (
            !$role instanceof OrgRole
            || (string) $role->getOrganisation()?->getId()
                !== (string) $organisation->getId()
        ) {
            throw new \InvalidArgumentException(
                'De gekozen rol hoort niet bij jouw organisatie.'
            );
        }

        return $role;
    }

    private function findMemberForOrganisation(
        string $memberId,
        Organisation $organisation
    ): OrgMember {
        if (!Uuid::isValid($memberId)) {
            throw new \InvalidArgumentException(
                'Ongeldige medewerker-ID.'
            );
        }

        $member = $this->entityManager->find(
            OrgMember::class,
            Uuid::fromString($memberId)
        );

        if (
            !$member instanceof OrgMember
            || (string) $member->getOrganisation()?->getId()
                !== (string) $organisation->getId()
        ) {
            throw new \InvalidArgumentException(
                'De medewerker hoort niet bij jouw organisatie.'
            );
        }

        return $member;
    }
}
