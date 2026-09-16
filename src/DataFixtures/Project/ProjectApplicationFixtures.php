<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\ProfileFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectApplicationFixtures extends Fixture implements DependentFixtureInterface 
{
    public const TEST_PROJECT_APPLICATION = "test-project-application";

    public function load(ObjectManager $manager): void
    {
        $projectApplication = new ProjectApplication();
        $projectApplication->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $projectApplication->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE, Project::class));
        $projectApplication->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_PROJECT_APPLICATIONS_STATUS_REFERENCE, Status::class));
        $manager->persist($projectApplication);

        $manager->flush();

        $this->addReference(SELF::TEST_PROJECT_APPLICATION,$projectApplication);
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\Account\ProfileFixtures',
            'App\DataFixtures\Common\StatusFixtures',
            'App\DataFixtures\Project\ProjectFixtures'
        );
    }
}
