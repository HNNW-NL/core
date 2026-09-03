<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Account\Account;
use App\Entity\Common\Status;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class AccountFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $account = new Account();
        $account->setUsername("TestUser");
        $account->setEmail("Test@test.nl");
        $account->setPasswordHash(password_hash("test1234", PASSWORD_DEFAULT));
        $account->setStatus($this->getReference(StatusFixtures::DEFAULT_STATUS_REFERENCE, Status::class));
        $manager->persist($account);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array('App\DataFixtures\StatusFixtures');
    }
}
