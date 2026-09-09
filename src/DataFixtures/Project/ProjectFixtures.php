<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\AccountFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\DataFixtures\Org\OrganisationFixtures;
use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $project = new Project();
        $project->setOwnerAccount($this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class));
        $project->setOwnerOrganisation($this->getReference(OrganisationFixtures::TEST_ORGANISATION, Organisation::class));
        $project->setTitle("Test Project");
        $project->setSlug("Test-Project");
        $project->setStatus($this->getReference(StatusFixtures::TEST_DEFAULT_PROJECT_STATUS_REFERENCE, Status::class));
        $project->setSummary("Een test project");
        $project->setStartDate(new DateTimeImmutable("now"));
        $project->setCapacity(10);
        $project->setVisibility("true");
        $project->setDescription("Dit is een test project");

        $manager->persist($project);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures',
            'App\DataFixtures\Org\OrganisationFixtures'
        );
    }
}
