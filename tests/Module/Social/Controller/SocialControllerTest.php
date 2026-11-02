<?php

namespace App\Tests\Module\Social\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SocialControllerTest extends WebTestCase
{
    // index

    public function testShouldDisplayIndex(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // categories

    public function testShouldDisplayCategories(): void
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

    public function testWhenCategoryDoesntExistsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenCategoryExistsShouldDisplayViewCategory(): void
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

    public function testWhenPostDoesntExistsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenPostExistsShouldDisplayViewPost(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
