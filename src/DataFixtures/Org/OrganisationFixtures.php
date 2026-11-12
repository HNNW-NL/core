<?php

namespace App\DataFixtures\Org;

use App\DataFixtures\Common\StatusFixtures;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrganisationFixtures extends Fixture implements DependentFixtureInterface
{
    public const TEST_ORGANISATION = 'test-organisation';
    public const TEST_ORGANISATION_2 = 'test-organisation-2';

    public function load(ObjectManager $manager): void
    {
        $organisation = new Organisation();
        $organisation->setName("Test Organistion");
        $organisation->setSlug("Test-Organistion");
        $organisation->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE, Status::class));
        $organisation->setDescription("Een test omschrijving van de organisatie");
        $manager->persist($organisation);

        $secondOrganisation = new Organisation();
        $secondOrganisation->setName("Second Test Organisation");
        $secondOrganisation->setSlug("second-test-organisation");
        $secondOrganisation->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE, Status::class));
        $secondOrganisation->setDescription("Een tweede testorganisatie");
        $manager->persist($secondOrganisation);

        $manager->flush();

        $this->addReference(self::TEST_ORGANISATION, $organisation);
        $this->addReference(self::TEST_ORGANISATION_2, $secondOrganisation);
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\Common\StatusFixtures');
    }
}
