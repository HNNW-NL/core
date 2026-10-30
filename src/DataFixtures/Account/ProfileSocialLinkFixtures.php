<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Profile;
use App\Entity\Account\ProfileSocialLink;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProfileSocialLinkFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $profileSocialLink = new ProfileSocialLink(); 
        $profileSocialLink->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $profileSocialLink->setLabel("x");      
        $profileSocialLink->setUrl("x.com");
        $manager->persist($profileSocialLink);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\ProfileFixtures'
        );
    }
}
