<?php

namespace App\DataFixtures\Common;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Common\Status;

class StatusFixtures extends Fixture
{

    public const TEST_DEFAULT_ACCOUNT_STATUS_REFERENCE = 'default-status';
    public const TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE = 'default-organisation-status';
    public const TEST_DEFAULT_PROJECT_STATUS_REFERENCE = 'default-project-status';

    public function load(ObjectManager $manager): void
    {
        $defaultStatus = new Status();
        $defaultStatus->setName('active');
        $defaultStatus->setScope('account');
        $defaultStatus->setColourHex('#10B981');
        $manager->persist($defaultStatus);

        $defaultOrgStatus = new Status();
        $defaultOrgStatus->setName('active');
        $defaultOrgStatus->setScope('organisation');
        $defaultOrgStatus->setColourHex('#10B981');
        $manager->persist($defaultOrgStatus);

        $defaultProjectStatus = new Status();
        $defaultProjectStatus->setName('active');
        $defaultProjectStatus->setScope('project');
        $defaultProjectStatus->setColourHex('#10B981');
        $manager->persist($defaultProjectStatus);

        $manager->flush();

        $this->addReference(self::TEST_DEFAULT_PROJECT_STATUS_REFERENCE, $defaultProjectStatus);
        $this->addReference(self::TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE, $defaultOrgStatus);
        $this->addReference(self::TEST_DEFAULT_ACCOUNT_STATUS_REFERENCE, $defaultStatus);
    }
}
