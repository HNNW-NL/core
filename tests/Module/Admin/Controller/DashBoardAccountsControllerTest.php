<?php

namespace App\Tests\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashBoardAccountsControllerTest extends WebTestCase
{
    // index

    public function testWhenUserIsNotAdminShouldRedirectFromIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // modify

    public function testWhenUserIsNotAdminShouldRedirectFromModify(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndAccountExistShouldDisplayModify(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndFormSubmitShouldModifyAccount(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsNotAdminAndModifyFormSubmitShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndAccountDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
