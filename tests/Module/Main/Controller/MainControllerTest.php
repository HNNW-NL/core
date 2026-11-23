<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MainControllerTest extends WebTestCase
{
    public function testLoggedOutUserSeesLoginLinkWithoutAccountData(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('a.nav-button[href="/account"]');
        $this->assertSelectorTextContains('a.nav-button[href="/auth/login"]', 'Inloggen');
        $this->assertStringNotContainsString('TestUser', $crawler->text());
    }

    public function testHomepageResponseIsNotSharedCached(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertSame('no-cache', $client->getResponse()->headers->get('Pragma'));
    }

    public function testLogoutClearsAccountSession(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $session = $client->getRequest()->getSession();
        $session->set('account_id', 'test-account');
        $session->set('dev_account_id', 'dev-account');

        $client->request('GET', '/auth/logout');

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorNotExists('a.nav-button[href="/account"]');
        $this->assertSelectorTextContains('a.nav-button[href="/auth/login"]', 'Inloggen');
    }

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
