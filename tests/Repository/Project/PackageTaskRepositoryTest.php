<?php

namespace App\Tests\Repository\Project;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PackageTaskRepositoryTest extends KernelTestCase
{
    // findOneAvailableForProject

    public function testWhenProjectIsNotAssignedAndNotDeletedShouldReturnProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectIsAssignedAndNotDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectIsNotAssignedAndDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // findOneForProject

    public function testWhenProjectNotDeletedShouldReturnProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // findVisibleForProjectSlug

    public function testWhenProjectIsNotDeletedShouldReturnProjectAndNotDeletedWorkPackagesAndNotDeletedPackageTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectIsDeletedShouldReturnEmptyResult(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // save

    public function testShouldSavePackageTask(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // isAssignedToProfile

    public function testWhenPackageTaskIsAssignedToProfileShouldReturnTrue(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenPackageTaskIsAssignedNotToProfileShouldReturnFalse(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
