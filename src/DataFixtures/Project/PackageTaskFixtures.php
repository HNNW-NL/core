<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\ProfileFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Project\PackageTask;
use App\Entity\Project\WorkPackage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

class PackageTaskFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $packageTask = new PackageTask();
        $packageTask->setAssignedProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $packageTask->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_PACKAGE_TASK_STATUS_REFERENCE, Status::class));
        $packageTask->setWorkPackage($this->getReference(WorkPackageFixtures::TEST_WORK_PACKAGE, WorkPackage::class));
        $packageTask->setTitle("test package");
        $packageTask->setSlug("test-package");
        $manager->persist($packageTask);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\Account\ProfileFixtures',
            'App\DataFixtures\Common\StatusFixtures',
            'App\DataFixtures\Project\WorkPackageFixtures'
        );
    }
}
