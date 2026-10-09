<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Org\Organisation;
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
        $organisation = $this->getCurrentOrganisation();
        $this->requireBeheerder($organisation);

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
        $this->entityManager->flush();

        return $member;
    }

    public function changeRole(string $memberId, string $roleId): OrgMember
    {
        $organisation = $this->getCurrentOrganisation();
        $this->requireBeheerder($organisation);

        $member = $this->findMemberForOrganisation(
            $memberId,
            $organisation
        );

        $role = $this->findRoleForOrganisation(
            $roleId,
            $organisation
        );

        $member->setOrgRole($role);
        $this->entityManager->flush();

        return $member;
    }

    public function removeMember(string $memberId): void
    {
        $organisation = $this->getCurrentOrganisation();
        $this->requireBeheerder($organisation);

        $member = $this->findMemberForOrganisation(
            $memberId,
            $organisation
        );

        $this->entityManager->remove($member);
        $this->entityManager->flush();
    }

    private function requireBeheerder(Organisation $organisation): void
    {
        $account = $this->getCurrentAccount();

        $memberships = $this->entityManager
            ->getRepository(OrgMember::class)
            ->findBy([
                'organisation' => $organisation,
                'account' => $account,
            ]);

        foreach ($memberships as $membership) {
            $roleName = $membership->getOrgRole()?->getName();

            if (
                is_string($roleName)
                && mb_strtolower(trim($roleName)) === 'beheerder'
            ) {
                return;
            }
        }

        throw new \RuntimeException(
            'Geen toegang: alleen een beheerder mag medewerkers beheren.'
        );
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

    private function getCurrentOrganisation(): Organisation
    {
        $account = $this->getCurrentAccount();

        $memberships = $account->getOrgMemberships();

        if ($memberships->isEmpty()) {
            throw new \RuntimeException(
                'Je bent geen lid van een organisatie.'
            );
        }

        $membership = $memberships->first();
        $organisation = $membership->getOrganisation();

        if (!$organisation instanceof Organisation) {
            throw new \RuntimeException(
                'De organisatie kon niet worden bepaald.'
            );
        }

        return $organisation;
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
