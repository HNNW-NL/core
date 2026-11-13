<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Availability;
use App\Entity\Account\Profile;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AvailiabilityFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Monday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("11:00"));
        $avalability->setEndTime(new DateTimeImmutable("15:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);

        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Tuesday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("8:00"));
        $avalability->setEndTime(new DateTimeImmutable("16:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);
        
        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Wednesday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("10:00"));
        $avalability->setEndTime(new DateTimeImmutable("17:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);

        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Thursday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("10:00"));
        $avalability->setEndTime(new DateTimeImmutable("17:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);

        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Friday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("10:00"));
        $avalability->setEndTime(new DateTimeImmutable("17:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);

        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Saturday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("10:00"));
        $avalability->setEndTime(new DateTimeImmutable("17:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);

        $avalability = new Availability();
        $avalability->setAvailabilityType("office");        
        $avalability->setDayOfWeek("Sunday");        
        $avalability->setValidFrom(new DateTimeImmutable("now"));
        $avalability->setStartTime(new DateTimeImmutable("10:00"));
        $avalability->setEndTime(new DateTimeImmutable("17:00"));
        $avalability->setProfile($this->getReference(ProfileFixtures::TEST_PROFILE_REFERENCE, Profile::class));
        
        $manager->persist($avalability);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\ProfileFixtures'
        );
    }
}
