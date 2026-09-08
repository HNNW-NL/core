<?php

namespace App\DataFixtures\Org;

use App\DataFixtures\Account\AccountFixtures;
use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrgMemberFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $orgMember = new OrgMember();
        $orgMember->setAccount($this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class));
        $orgMember->setOrganisation($this->getReference(OrganisationFixtures::TEST_ORGANISATION, Organisation::class));
        $orgMember->setOrgRole($this->getReference(OrgRoleFixtures::TEST_OWNER_ROLE, OrgRole::class));
        $manager->persist($orgMember);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures',
            'App\DataFixtures\Org\OrganisationFixtures',
            'App\DataFixtures\Org\OrgRoleFixtures'
        );
    }
}
