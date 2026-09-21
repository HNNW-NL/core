<?php

namespace App\DataFixtures\Log;

use App\Entity\Log\SystemLog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SystemLogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setRoute("/");
        $systemLog->setMethod("get");
        $systemLog->setRequestIp("127.0.0.1");
        $systemLog->setMessage("A system message");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("A second system log");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $manager->flush();
    }
}
