<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MainControllerTest extends WebTestCase
{
    // Index

    public function testShouldDisplayHome(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Home');
    }

    //About

    public function testShouldDisplayAbout(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/about');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'About');
    }

    // viewProfile

    public function testWhenUserDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserExistShouldDisplayUserName(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserExistShouldDisplayProfielFoto(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserExistShouldDisplayLocation(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserExistShouldDisplayStatus(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testWhenUserExistShouldDisplayLastLogin(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testUserExistShouldDisplayExpertise(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testUserExistShouldDisplaySocials(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }

    public function testUserExistInShouldDisplayAchievements(): void
    {
        //Reminder that test is not yet implemented
        $this->markTestIncomplete("Test is nog niet geïmplementeerd.");
    }
}
