<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Skill;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SkillFixtures extends Fixture
{
    public const TEST_SKILL_REFERENCE = "test-skill";

    public function load(ObjectManager $manager): void
    {
        $skill = new Skill();
        $skill->setCategory("Programming");
        $skill->setName("PHP");
        $skill->setSlug("PHP");
        $manager->persist($skill);

        $manager->flush();

         $this->addReference(self::TEST_SKILL_REFERENCE, $skill);
        
    }
}
