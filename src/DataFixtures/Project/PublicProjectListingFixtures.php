<?php

namespace App\DataFixtures\Project;

use App\DataFixtures\Account\AccountFixtures;
use App\DataFixtures\Account\ProfileFixtures;
use App\DataFixtures\Common\StatusFixtures;
use App\DataFixtures\Org\OrganisationFixtures;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PublicProjectListingFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $account = $this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class);
        $organisation = $this->getReference(OrganisationFixtures::TEST_ORGANISATION, Organisation::class);
        $publishedStatus = $this->getReference(StatusFixtures::TEST_PUBLISHED_PROJECT_STATUS_REFERENCE, Status::class);
        $draftStatus = $this->getReference(StatusFixtures::TEST_DEFAULT_PROJECT_STATUS_REFERENCE, Status::class);

        for ($number = 1; $number <= 21; ++$number) {
            $project = new Project();
            $project
                ->setOwnerAccount($account)
                ->setOwnerOrganisation($organisation)
                ->setTitle(sprintf('MS04 openbaar project %02d', $number))
                ->setSlug(sprintf('ms04-openbaar-project-%02d', $number))
                ->setStatus($publishedStatus)
                ->setSummary('Een project met zoekterm samenvatting')
                ->setStartDate(new DateTimeImmutable('-1 day'))
                ->setCapacity($number === 21 ? 1 : 10)
                ->setVisibility('public')
                ->setDescription('Een openbaar project voor de pagineringsacceptatietest.');
            $project->publish();
            $manager->persist($project);

            if ($number === 21) {
                $participant = new ProjectParticipant();
                $participant
                    ->setProject($project)
                    ->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class))
                    ->setStatus($this->getReference(
                        StatusFixtures::TEST_DEFAULT_PROJECT_PARTICIPANTS_STATUS_REFERENCE,
                        Status::class,
                    ));
                $participant->join();
                $manager->persist($participant);
            }
        }

        $draft = new Project();
        $draft
            ->setOwnerAccount($account)
            ->setOwnerOrganisation($organisation)
            ->setTitle('MS04 conceptproject')
            ->setSlug('ms04-conceptproject')
            ->setStatus($draftStatus)
            ->setSummary('Een concept met zoekterm samenvatting')
            ->setStartDate(new DateTimeImmutable('-1 day'))
            ->setCapacity(10)
            ->setVisibility('public')
            ->setDescription('Dit concept mag niet in openbare resultaten verschijnen.');
        $manager->persist($draft);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ProjectFixtures::class,
            ProfileFixtures::class,
        ];
    }
}
