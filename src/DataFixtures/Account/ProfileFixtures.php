<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class ProfileFixtures extends Fixture implements DependentFixtureInterface
{

    public const TEST_PROFILE_REFERENCE = 'test-profile';
    public const TEST_ADMIN_PROFILE_REFERENCE = 'test-admin-profile';

    public function load(ObjectManager $manager): void
    {
        $profile = new Profile();
        $profile->setAccount($this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class));
        $profile->setFirstName("Test");
        $profile->setLastName("User");
        $profile->setAvatarUrl("none");

        $manager->persist($profile);
            
        $adminProfile = new Profile();
        $adminProfile->setAccount($this->getReference(AccountFixtures::ADMIN_ACCOUNT_REFERENCE, Account::class));
        $adminProfile->setFirstName("Admin");
        $adminProfile->setLastName("User");
        $adminProfile->setAvatarUrl("none");
        
        $manager->persist($adminProfile);

        $manager->flush();
        
        $this->addReference(self::TEST_PROFILE_REFERENCE, $profile);
        $this->addReference(self::TEST_ADMIN_PROFILE_REFERENCE, $adminProfile);
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures'
        );
    }
}
