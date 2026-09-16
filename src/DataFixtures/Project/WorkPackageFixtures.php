<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\ProfileFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class WorkPackageFixtures extends Fixture implements DependentFixtureInterface
{
    public const TEST_WORK_PACKAGE = "test-work-package";

    public function load(ObjectManager $manager): void
    {
        $workPackage = new WorkPackage();

        $workPackage->setProject($this->getReference(ProjectFixtures::TEST_PROJECT_REFERENCE, Project::class));
        $workPackage->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_WORK_PACKAGE_STATUS_REFERENCE, Status::class));
        
        $workPackage->setTitle("Work Package");
        $workPackage->setSlug("ork-package");
        $manager->persist($workPackage);

        $manager->flush();

        $this->addReference(SELF::TEST_WORK_PACKAGE,$workPackage);
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\Project\ProjectFixtures',
            'App\DataFixtures\Common\StatusFixtures'
        );
    }
}
