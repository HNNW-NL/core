<?php

namespace App\Tests\Repository\Project;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PackageTaskRepositoryTest extends KernelTestCase
{
    // findOneAvailableForProject

    public function testWhenProjectIsNotAssignedAndNotDeletedShouldReturnProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectIsAssignedAndNotDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectIsNotAssignedAndDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // findOneForProject

    public function testWhenProjectNotDeletedShouldReturnProject(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // findVisibleForProjectSlug

    public function testWhenProjectIsNotDeletedShouldReturnProjectAndNotDeletedWorkPackagesAndNotDeletedPackageTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenProjectIsDeletedShouldReturnEmptyResult(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // save

    public function testShouldSavePackageTask(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // isAssignedToProfile

    public function testWhenPackageTaskIsAssignedToProfileShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenPackageTaskIsAssignedNotToProfileShouldReturnFalse(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
