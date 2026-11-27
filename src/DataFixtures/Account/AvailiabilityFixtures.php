<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Availability;
use App\Entity\Account\Profile;
use App\Enum\AvailabilityType;
use App\Enum\DayOfWeek;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AvailiabilityFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $profile = $this->getReference(
            ProfileFixtures::TEST_PROFILE_REFERENCE,
            Profile::class
        );

        $schedule = [
            [DayOfWeek::MONDAY, '11:00', '15:00'],
            [DayOfWeek::TUESDAY, '08:00', '16:00'],
            [DayOfWeek::WEDNESDAY, '10:00', '17:00'],
            [DayOfWeek::THURSDAY, '10:00', '17:00'],
            [DayOfWeek::FRIDAY, '10:00', '17:00'],
            [DayOfWeek::SATURDAY, '10:00', '17:00'],
            [DayOfWeek::SUNDAY, '10:00', '17:00'],
        ];

        $validFrom = new DateTimeImmutable('now');

        foreach ($schedule as [$day, $startTime, $endTime]) {
            $availability = new Availability();
            $availability->setAvailabilityType(AvailabilityType::AVAILABLE);
            $availability->setDayOfWeek($day);
            $availability->setValidFrom($validFrom);
            $availability->setStartTime(new DateTimeImmutable($startTime));
            $availability->setEndTime(new DateTimeImmutable($endTime));
            $availability->setProfile($profile);

            $manager->persist($availability);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ProfileFixtures::class,
        ];
    }
}
