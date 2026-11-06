<?php

namespace App\Module\Org\Service;

use App\Entity\Account\Account;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Org\Organisation;
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
        return $this->manage(fn (Organisation $organisation): OrgMember => $this->add($organisation, $email, $roleId));
    }

    private function add(Organisation $organisation, string $email, string $roleId): OrgMember
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
        return $this->manage(fn (Organisation $organisation): OrgMember => $this->updateRole($organisation, $memberId, $roleId));
    }

    private function updateRole(Organisation $organisation, string $memberId, string $roleId): OrgMember
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

        if (!self::isBeheerder($role, $organisation)) {
            $this->requireAnotherBeheerder($organisation, $member);
        }

        $member->setOrgRole($role);
        $this->entityManager->flush();

        return $member;
    }

    public function removeMember(string $memberId): void
    {
        $this->manage(fn (Organisation $organisation) => $this->remove($organisation, $memberId));
    }

    private function remove(Organisation $organisation, string $memberId): void
    {
        $member = $this->findMemberForOrganisation(
            $memberId,
            $organisation
        );

        $this->requireAnotherBeheerder($organisation, $member);
        $this->entityManager->remove($member);
        $this->entityManager->flush();
    }

    private function manage(callable $operation): mixed
    {
        $organisation = $this->getCurrentOrganisation();

        try {
            return $this->entityManager->getConnection()->transactional(function () use ($organisation, $operation): mixed {
                $this->entityManager->lock($organisation, LockMode::PESSIMISTIC_WRITE);
                $this->requireBeheerder($organisation);

                return $operation($organisation);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new \InvalidArgumentException('Dit account heeft deze rol al binnen de organisatie.', 0, $e);
        }
    }

    public static function isBeheerder(?OrgRole $role, Organisation $organisation): bool
    {
        return $role !== null
            && (string) $role->getOrganisation()?->getId() === (string) $organisation->getId()
            && mb_strtolower(trim($role->getName() ?? '')) === 'beheerder';
    }

    private function requireAnotherBeheerder(Organisation $organisation, OrgMember $member): void
    {
        if (!self::isBeheerder($member->getOrgRole(), $organisation)) {
            return;
        }

        $memberships = $this->entityManager->getRepository(OrgMember::class)->findBy(['organisation' => $organisation]);
        foreach ($memberships as $membership) {
            if ($membership !== $member && self::isBeheerder($membership->getOrgRole(), $organisation)) {
                return;
            }
        }

        throw new \InvalidArgumentException('De organisatie moet minstens één beheerder behouden.');
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

        $isBeheerder = false;
        foreach ($memberships as $membership) {
            // The organisation lookup may have loaded this membership before the lock.
            $this->entityManager->refresh($membership);
            $isBeheerder = $isBeheerder || self::isBeheerder($membership->getOrgRole(), $organisation);
        }

        if ($isBeheerder) {
            return;
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

        foreach ($memberships as $membership) {
            if ((string) $membership->getOrganisation()?->getId() !== (string) $organisation->getId()) {
                throw new \RuntimeException(
                    'Medewerkers beheren vereist een actieve organisatie als je lid bent van meerdere organisaties.'
                );
            }
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
