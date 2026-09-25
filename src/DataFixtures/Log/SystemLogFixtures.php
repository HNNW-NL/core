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
        $systemLog->setLevel("");
        $systemLog->setMessage("A system message");
        $systemLog->setContextJson([]);
        
        $manager->persist($systemLog);

        $manager->flush();
    }
}
