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

        $auditLog = new AuditLog();
        $auditLog->setAction("test");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test2");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test3");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test4");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test5");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test6");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test7");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test8");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test9");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test10");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test11");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test12");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test13");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test14");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test15");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test16");
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


        $auditLog = new AuditLog();
        $auditLog->setAction("test17");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test18");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test19");
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

        $auditLog = new AuditLog();
        $auditLog->setAction("test20");
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
