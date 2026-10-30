<?php

namespace App\DataFixtures\Project;

use App\Entity\Project\Project;
use App\Entity\Project\ProjectRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectRoleFixtures extends Fixture implements DependentFixtureInterface
{
    public const TEST_PROJECT_ROLE = "test_project_role";

    public function load(ObjectManager $manager): void
    {
        $projectRole = new ProjectRole();
        $projectRole->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE,Project::class));
        $projectRole->setName("Developer");
        $manager->persist($projectRole);

        $manager->flush();

        $this->addReference(SELF::TEST_PROJECT_ROLE,$projectRole);
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Project\ProjectFixtures'
        );
    }
}
