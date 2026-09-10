<?php

namespace App\Tests\Module\Social\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SocialControllerTest extends WebTestCase
{
    // index

    public function testWhenNotLoggedInShouldBeRedirectedFromIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // categories

    public function testWhenNotLoggedInShouldBeRedirectedFromCategories(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayCategories(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // viewCategory

    public function testWhenNotLoggedInShouldBeRedirectedFromViewCategory(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndCategoryDoesntExistsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndCategoryExistsShouldDisplayViewCategory(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // createPost

    public function testWhenNotLoggedInShouldBeRedirectedFromCreatePost(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayCreatePost(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndSubmitFormShouldCreatePost(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // viewPost

    public function testWhenNotLoggedInShouldBeRedirectedFromViewPost(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndPostDoesntExistsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndPostExistsShouldDisplayViewPost(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
