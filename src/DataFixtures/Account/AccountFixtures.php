<?php

namespace App\DataFixtures\Account;

use App\DataFixtures\Common\StatusFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Account\Account;
use App\Entity\Common\Status;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class AccountFixtures extends Fixture implements DependentFixtureInterface
{
    public const TEST_ACCOUNT_REFERENCE = 'test-account';
    public const ADMIN_ACCOUNT_REFERENCE = 'admin-account';

    public function load(ObjectManager $manager): void
    {
        $account = new Account();
        $account->setUsername("TestUser");
        $account->setEmail("Test@test.nl");
        $account->setPasswordHash(password_hash("test1234", PASSWORD_DEFAULT));
        $account->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_ACCOUNT_STATUS_REFERENCE, Status::class));
        $manager->persist($account);

        $admin = new Account();
        $admin->setUsername("AdminUser");
        $admin->setEmail("Admin@test.nl");
        $admin->setPasswordHash(password_hash("test1234", PASSWORD_DEFAULT));
        $admin->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_ACCOUNT_STATUS_REFERENCE, Status::class));
        $manager->persist($admin);

        $manager->flush();

        $this->addReference(self::TEST_ACCOUNT_REFERENCE, $account);
        $this->addReference(self::ADMIN_ACCOUNT_REFERENCE, $admin);
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\Common\StatusFixtures');
    }
}
