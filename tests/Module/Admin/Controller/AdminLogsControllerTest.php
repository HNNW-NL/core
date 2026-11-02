<?php

namespace App\Tests\Module\Admin\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class AdminLogsControllerTest extends ApiTestCase
{

    //system 

    public function testWhenUserIsNotAdminShouldGiveErrorFromSystem(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldGiveSystemlogAssJson(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // audit

    public function testWhenUserIsNotAdminShouldGiveErrorFromAudit(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldGiveAuditlogAssJson(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
