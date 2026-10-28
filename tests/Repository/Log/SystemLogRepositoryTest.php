<?php

namespace App\Tests\Repository\Log;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SystemLogRepositoryTest extends KernelTestCase
{
    // findLatest

    public function testWhenMoreThanLimitExistShouldReturnLatestCreated(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
