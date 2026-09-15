<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\ProfileFixtures;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectUpdate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectUpdateFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $projectUpdate = new ProjectUpdate();
        $projectUpdate->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE,Project::class));
        $projectUpdate->setAuthor($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE,Profile::class));
        $projectUpdate->setTitle("A update");
        $manager->persist($projectUpdate);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Project\ProjectFixtures',
            'App\DataFixtures\Account\ProfileFixtures'
        );
    }
}
