<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Profile;
use App\Entity\Account\ProfileSkill;
use App\Entity\Account\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProfileSkillFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $profileSkill = new ProfileSkill();
        $profileSkill->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $profileSkill->setSkill($this->getReference(SkillFixtures::TEST_SKILL_REFERENCE, Skill::class));
        $profileSkill->setProficiencyLevel("beginner");
        $manager->persist($profileSkill);

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
