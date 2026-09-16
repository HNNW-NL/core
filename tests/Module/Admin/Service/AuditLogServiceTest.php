<?php

namespace App\Tests\Module\Admin\Service;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditLogServiceTest extends KernelTestCase
{
    // log

    public function testWhenAccountExistShouldCreateAuditLogWithAccount(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenAccountDoesntExistShouldCreateAuditLogWithoutAccount(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
