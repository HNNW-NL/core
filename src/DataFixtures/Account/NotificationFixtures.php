<?php

namespace App\DataFixtures\Account;

use App\Entity\Account\Account;
use App\Entity\Account\Notification;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class NotificationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $notification = new Notification();

        $notification->setAccount($this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class));
        $notification->setSenderAccount($this->getReference(AccountFixtures::ADMIN_ACCOUNT_REFERENCE, Account::class));
        $notification->setTitle("hello");
        $notification->setType("Introduction");
        $notification->setMessage("hello");
        $manager->persist($notification);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures'
        );
    }
}
