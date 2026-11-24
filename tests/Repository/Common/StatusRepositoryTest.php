<?php

namespace App\Tests\Repository\Common;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StatusRepositoryTest extends KernelTestCase
{
    // findOneByScopeAndName

    public function testWhenStatusWithScopeAndNameExistShouldReturnOneResult(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenStatusWithScopeAndNameDoesntExistShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // findFirstByScopeAndNames

    public function testWhenAtLeastOneStatusWithScopeAndNamesExistShouldReturnFirstResult(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenStatusWithScopeAndSecondNameButNotFirstInArrayNamesExistShouldReturnResultWithSecondName(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenStatusWithScopeAndFirstAndSecondNameButInArrayNamesExistShouldReturnResultWithFirstName(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWheStatusWithScopeAndNamesDoesntExistShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
