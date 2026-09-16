<?php

namespace App\Tests\Repository\Project;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProjectRepositoryTest extends KernelTestCase
{
    // findOneVisibleBySlug

    public function testWhenProjectIsNotDeletedShouldReturnProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectIsDeletedShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenProjectDoesntExistShouldReturnNull(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
