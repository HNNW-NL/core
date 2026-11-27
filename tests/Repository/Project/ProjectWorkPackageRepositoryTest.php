<?php

namespace App\Tests\Repository\Project;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectWorkPackageRepositoryTest extends KernelTestCase
{
    // findVisibleForProjectSlug

    public function testWhenProjectIsNotDeletedShouldReturnWorkPackages(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectIsNotDeletedAndWorkPackagesAreDeletedShouldReturnEmptyResult(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectIsDeletedShouldReturnEmptyResult(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectDoesntExistShouldReturnEmptyResult(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
