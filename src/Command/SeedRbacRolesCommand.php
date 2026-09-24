<?php

namespace App\Command;

use App\Entity\Admin\AdminRole;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgRole;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Zet de basis-rollen (Admin, Organisation Admin, User) met de bijbehorende
 * permissions-mask neer. Idempotent: bestaande rollen worden bijgewerkt in
 * plaats van gedupliceerd, dus dit commando mag je zo vaak draaien als je wilt.
 *
 * bin/console app:rbac:seed-roles
 */
#[AsCommand(name: 'app:rbac:seed-roles', description: 'Seed de basis RBAC-rollen (Admin, Organisation Admin, User)')]
final class SeedRbacRolesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Platform-brede "Admin" rol
        $adminRole = $this->entityManager->getRepository(AdminRole::class)->findOneBy(['name' => 'Admin']);
        if ($adminRole === null) {
            $adminRole = new AdminRole();
            $adminRole->setName('Admin');
            $this->entityManager->persist($adminRole);
        }
        $adminRole->setPermissionsMask(Permission::maskFor([
            Permission::ADMIN_VIEW_DASHBOARD,
            Permission::ADMIN_MANAGE_ACCOUNTS,
            Permission::ADMIN_MANAGE_ORGANISATIONS,
            Permission::ADMIN_VIEW_LOGS,
            Permission::ADMIN_MANAGE_SETTINGS,
            Permission::ADMIN_MANAGE_STAFF,
        ]));
        $io->writeln(sprintf('AdminRole "Admin" -> mask %d', $adminRole->getPermissionsMask()));

        // 2. Per organisatie: "Organisation Admin" (alles) en "User" (alleen bekijken)
        $organisations = $this->entityManager->getRepository(Organisation::class)->findAll();
        $orgRoleRepo = $this->entityManager->getRepository(OrgRole::class);

        foreach ($organisations as $organisation) {
            $orgAdminRole = $orgRoleRepo->findOneBy(['organisation' => $organisation, 'name' => 'Organisation Admin']);
            if ($orgAdminRole === null) {
                $orgAdminRole = new OrgRole();
                $orgAdminRole->setOrganisation($organisation);
                $orgAdminRole->setName('Organisation Admin');
                $this->entityManager->persist($orgAdminRole);
            }
            $orgAdminRole->setPermissionsMask(Permission::maskFor([
                Permission::ORG_VIEW,
                Permission::ORG_EDIT,
                Permission::ORG_MANAGE_MEMBERS,
                Permission::ORG_MANAGE_ROLES,
                Permission::ORG_MANAGE_PROJECTS,
                Permission::ORG_DELETE,
            ]));

            $userRole = $orgRoleRepo->findOneBy(['organisation' => $organisation, 'name' => 'User']);
            if ($userRole === null) {
                $userRole = new OrgRole();
                $userRole->setOrganisation($organisation);
                $userRole->setName('User');
                $this->entityManager->persist($userRole);
            }
            $userRole->setPermissionsMask(Permission::maskFor([
                Permission::ORG_VIEW,
            ]));

            $io->writeln(sprintf(
                'Organisatie "%s": Organisation Admin -> mask %d, User -> mask %d',
                (string) $organisation->getName(),
                $orgAdminRole->getPermissionsMask(),
                $userRole->getPermissionsMask()
            ));
        }

        $this->entityManager->flush();

        $io->success('RBAC-rollen zijn geseed.');

        return Command::SUCCESS;
    }
}