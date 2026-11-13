<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Account;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use  App\Entity\Account\AccountSetting;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class AcountSettingFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $accountSetting = new AccountSetting();
        $accountSetting->setAccount($this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class));
        $manager->persist($accountSetting);

        $adminAccountSetting = new AccountSetting();
        $adminAccountSetting->setAccount($this->getReference(AccountFixtures::ADMIN_ACCOUNT_REFERENCE, Account::class));
        $manager->persist($adminAccountSetting);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures'
        );
    }
}
