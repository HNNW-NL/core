<?php

namespace App\Tests\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashBoardAccountsControllerTest extends WebTestCase
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

    // modify

    public function testWhenUserIsNotAdminShouldRedirectFromModify(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndAccountExistShouldDisplayModify(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndFormSubmitShouldModifyAccount(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsNotAdminAndModifyFormSubmitShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserIsAdminAndAccountDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
