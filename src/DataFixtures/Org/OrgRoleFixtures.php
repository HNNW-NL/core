<?php

namespace App\DataFixtures\Org;

use App\Entity\Org\Organisation;
use App\Entity\Org\OrgRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrgRoleFixtures extends Fixture implements DependentFixtureInterface
{
    public const TEST_OWNER_ROLE = 'owner-role';

    public function load(ObjectManager $manager): void
    {
        $orgRole = new OrgRole();
        $orgRole->setName('Owner');
        $orgRole->setOrganisation($this->getReference(OrganisationFixtures::TEST_ORGANISATION, Organisation::class));

        $manager->persist($orgRole);

        $manager->flush();

        $this->addReference(self::TEST_OWNER_ROLE, $orgRole);
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Org\OrganisationFixtures'
        );
    }
}
