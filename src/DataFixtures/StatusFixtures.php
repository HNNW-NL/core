<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Common\Status;

class StatusFixtures extends Fixture
{

    public const DEFAULT_STATUS_REFERENCE = 'default-status';

    public function load(ObjectManager $manager): void
    {
        $defaultStatus = new Status();
        $defaultStatus->setName('active');
        $defaultStatus->setScope('account');
        $defaultStatus->setColourHex('#10B981');
        $manager->persist($defaultStatus);

        $manager->flush();

        $this->addReference(self::DEFAULT_STATUS_REFERENCE, $defaultStatus);
    }
}
