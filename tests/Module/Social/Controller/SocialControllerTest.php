<?php

namespace App\Tests\Module\Social\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SocialControllerTest extends WebTestCase
{
    // index

    public function testShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // categories

    public function testShouldDisplayCategories(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // viewCategory

    public function testWhenNotLoggedInShouldBeRedirectedFromViewCategory(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenCategoryDoesntExistsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenCategoryExistsShouldDisplayViewCategory(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // createPost

    public function testWhenNotLoggedInShouldBeRedirectedFromCreatePost(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInShouldDisplayCreatePost(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenLoggedInAndSubmitFormShouldCreatePost(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    // viewPost

    public function testWhenPostDoesntExistsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenPostExistsShouldDisplayViewPost(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
