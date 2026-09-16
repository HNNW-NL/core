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

    public function load(ObjectManager $manager): void
    {
        $organisation = new Organisation();
        $organisation->setName("Test Organistion");
        $organisation->setSlug("Test-Organistion");
        $organisation->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_ORGANISATION_STATUS_REFERENCE, Status::class));
        $organisation->setDescription("Een test omschrijving van de organisatie");
        $manager->persist($organisation);

        $manager->flush();

        $this->addReference(self::TEST_ORGANISATION, $organisation);
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\Common\StatusFixtures');
    }
}
