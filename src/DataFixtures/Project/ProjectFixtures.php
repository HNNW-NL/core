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
    public const TEST_PROJECT_REFERENCE = "test-project";
    public const HOMEPAGE_PROJECT_REFERENCE = "homepage-project";

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
        $project->setEndDate(new DateTimeImmutable('2030-01-01'));
        $project->setCapacity(10);
        $project->setVisibility("public");
        $project->setDescription("Dit is een test project");

        $manager->persist($project);

        $homepageProject = new Project();
        $homepageProject->setOwnerAccount($this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class));
        $homepageProject->setOwnerOrganisation($this->getReference(OrganisationFixtures::TEST_ORGANISATION, Organisation::class));
        $homepageProject->setTitle("Gepubliceerd testproject");
        $homepageProject->setSlug("gepubliceerd-testproject");
        $homepageProject->setStatus($this->getReference(StatusFixtures::TEST_PUBLISHED_PROJECT_STATUS_REFERENCE, Status::class));
        $homepageProject->setSummary("Een openbaar project met open aanmelding");
        $homepageProject->setStartDate(new DateTimeImmutable("now"));
        $homepageProject->setEndDate(new DateTimeImmutable('2030-01-01'));
        $homepageProject->setCapacity(10);
        $homepageProject->setVisibility("public");
        $homepageProject->setDescription("Een gepubliceerd project voor de homepage.");
        $homepageProject->publish();
        $manager->persist($homepageProject);

        $manager->flush();

        $this->addReference(self::TEST_PROJECT_REFERENCE, $project);
        $this->addReference(self::HOMEPAGE_PROJECT_REFERENCE, $homepageProject);

    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures',
            'App\DataFixtures\Org\OrganisationFixtures',
            'App\DataFixtures\Common\StatusFixtures'
        );
    }
}
