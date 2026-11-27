<?php

namespace App\Tests\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashBoardOrganizationsControllerTest extends WebTestCase
{
    // index

    public function testWhenUserIsNotAdminShouldRedirectFromIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // Create

    public function testWhenUserIsNotAdminShouldRedirectFromCreate(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndOrganizationExistShouldDisplayCreate(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndCreateFormSubmitShouldCreateOrganization(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsNotAdminAndCreateFormSubmitShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // modify

    public function testWhenUserIsNotAdminShouldRedirectFromModify(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndOrganizationExistShouldDisplayModify(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndModifyFormSubmitShouldModifyOrganization(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsNotAdminAndModifyFormSubmitShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndOrganizationDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
