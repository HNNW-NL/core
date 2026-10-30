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
        $this->assertSelectorTextContains('h2', 'Projecten met open aanmelding');
        $this->assertSelectorExists('body > a.skip-link[href="#main-content"]');
        $this->assertStringNotContainsString(
            'Projectinformatie is op dit moment niet beschikbaar.',
            $crawler->text(),
        );
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
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayUserName(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayProfielFoto(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayLocation(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayStatus(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayLastLogin(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testUserExistShouldDisplayExpertise(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testUserExistShouldDisplaySocials(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testUserExistInShouldDisplayAchievements(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
