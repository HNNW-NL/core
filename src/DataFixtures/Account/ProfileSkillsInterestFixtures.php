<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Profile;
use App\Entity\Account\ProfileSkillsInterest;
use App\Entity\Account\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProfileSkillsInterestFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $profileSkillsInterest = new ProfileSkillsInterest();
        $profileSkillsInterest->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $profileSkillsInterest->setSkill($this->getReference(SkillFixtures::TEST_SKILL_REFERENCE, Skill::class));
        $manager->persist($profileSkillsInterest);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\ProfileFixtures',
            'App\DataFixtures\Account\SkillFixtures'
        );
    }
}
