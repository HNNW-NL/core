<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\UX\Toolkit\Dependency\DependencyInterface;

class ProfileExperienceFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $profileExperience = new ProfileExperience();
        $profileExperience->setJobTitle("A Job Title");
        $profileExperience->setDescription("A description");
        $profileExperience->setOrganisationName("A Organisation");
        $profileExperience->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        $profileExperience->setStartDate(new DateTimeImmutable("2020-01-01"));
        $manager->persist($profileExperience);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\ProfileFixtures'
        );
    }
}
