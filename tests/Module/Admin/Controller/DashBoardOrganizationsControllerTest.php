<?php

namespace App\Tests\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashBoardOrganizationsControllerTest extends WebTestCase
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

    // Create

    public function testWhenUserIsNotAdminShouldRedirectFromCreate(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndOrganizationExistShouldDisplayCreate(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndCreateFormSubmitShouldCreateOrganization(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsNotAdminAndCreateFormSubmitShouldGiveError(): void
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

    public function testWhenUserIsAdminAndOrganizationExistShouldDisplayModify(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndModifyFormSubmitShouldModifyOrganization(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsNotAdminAndModifyFormSubmitShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminAndOrganizationDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
