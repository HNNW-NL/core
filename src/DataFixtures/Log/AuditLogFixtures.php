<?php

namespace App\DataFixtures\Log;

use App\DataFixtures\Account\AccountFixtures;
use App\Entity\Account\Account;
use App\Entity\Log\AuditLog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AuditLogFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $account = $this->getReference(AccountFixtures::TEST_ACCOUNT_REFERENCE, Account::class);

        $auditLog = new AuditLog();
        $auditLog->setAction("login");
        $auditLog->setActorAccount($account);
        $auditLog->setActorEmail($account->getEmail());
        $auditLog->setActorUsername($account->getUsername());
        $auditLog->setEntityId("none");
        $auditLog->setEntityType("none");
        $auditLog->setOldValuesJson([0]);
        $auditLog->setNewValuesJson([1]);
        $auditLog->setRequestIp("127.0.0.0");        
        $auditLog->setUserAgent("firefox");
        
        $manager->persist($auditLog);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return array(
            'App\DataFixtures\Account\AccountFixtures'
        );
    }
}
