<?php

namespace App\DataFixtures\Admin;

use App\Entity\Admin\AdminRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AdminRoleFixtures extends Fixture
{
    public const DEFAULT_ADMIN_ROLE = "default-admin-role";

    public function load(ObjectManager $manager): void
    {
        $adminRole = new AdminRole();
        $adminRole->setName("default");
        $adminRole->setPermissionsMask(255);
        $manager->persist($adminRole);

        $manager->flush();

        $this->addReference(SELF::DEFAULT_ADMIN_ROLE,$adminRole);
    }
}
