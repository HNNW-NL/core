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

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 3");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 4");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 5");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 6");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 7");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 8");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 9");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 10");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 11");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 12");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 13");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 14");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 15");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 16");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 17");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 18");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 19");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 20");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $manager->persist($systemLog);$manager->persist($systemLog);$systemLog = new SystemLog();
        $systemLog->setCode(1);
        $systemLog->setLevel("1");
        $systemLog->setMessage("system log 21");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $manager->flush();
    }
}
