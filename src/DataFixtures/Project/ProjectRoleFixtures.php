<?php

namespace App\DataFixtures\Project;

use App\Entity\Project\Project;
use App\Entity\Project\ProjectRole;
use App\Security\ProjectPermission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectRoleFixtures extends Fixture implements DependentFixtureInterface
{
    public const TEST_PROJECT_ROLE = "test_project_role";
    public const TEST_PROJECT_ADMIN_ROLE = 'test_project_admin_role';

    public function load(ObjectManager $manager): void
    {
        $projectRole = new ProjectRole();
        $projectRole->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE,Project::class));
        $projectRole->setName("Developer");
        $manager->persist($projectRole);

        $administrator = (new ProjectRole())
            ->setProject($projectRole->getProject())
            ->setName('Project Administrator')
            ->setPermissionsMask(ProjectPermission::VIEW->value | ProjectPermission::INVITE->value);
        $manager->persist($administrator);

        $manager->flush();

        $this->addReference(SELF::TEST_PROJECT_ROLE,$projectRole);
        $this->addReference(self::TEST_PROJECT_ADMIN_ROLE, $administrator);
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Project\ProjectFixtures'
        );
    }
}
