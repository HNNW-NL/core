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
    public const TEST_PROJECT_ROLE_2 = "test_project_role_2";

    public function load(ObjectManager $manager): void
    {
        $projectRole = new ProjectRole();
        $projectRole->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE,Project::class));
        $projectRole->setName("Developer");
        $manager->persist($projectRole);

        $secondProjectRole = new ProjectRole();
        $secondProjectRole->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_2_REFERENCE, Project::class));
        $secondProjectRole->setName("Developer");
        $manager->persist($secondProjectRole);

        $manager->flush();

        $this->addReference(SELF::TEST_PROJECT_ROLE,$projectRole);
        $this->addReference(self::TEST_PROJECT_ROLE_2, $secondProjectRole);
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Project\ProjectFixtures'
        );
    }
}
