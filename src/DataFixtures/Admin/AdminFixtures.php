<?php

namespace App\DataFixtures\Admin;

use App\DataFixtures\Account\AccountFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\Entity\Account\Account;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Common\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class AdminFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $admin = new Admin();
        $admin->setAccount($this->getReference(AccountFixtures::ADMIN_ACCOUNT_REFERENCE,Account::class));
        $admin->setAdminRole($this->getReference(AdminRoleFixtures::DEFAULT_ADMIN_ROLE,AdminRole::class));
        $admin->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_ADMIN_STATUS_REFERENCE,Status::class));
        $manager->persist($admin);

        $manager->flush();
    }

    #[Override]
    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures',
            'App\DataFixtures\Common\StatusFixtures',
            'App\DataFixtures\Admin\AdminRoleFixtures'
        );
    }
}
