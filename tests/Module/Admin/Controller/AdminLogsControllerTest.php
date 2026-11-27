<?php

namespace App\Tests\Module\Admin\Controller;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class AdminLogsControllerTest extends ApiTestCase
{

    //system 

    public function testWhenUserIsNotAdminShouldGiveErrorFromSystem(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminShouldGiveSystemlogAssJson(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // audit

    public function testWhenUserIsNotAdminShouldGiveErrorFromAudit(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminShouldGiveAuditlogAssJson(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
