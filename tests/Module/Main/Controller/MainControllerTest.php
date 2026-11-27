<?php

namespace App\Tests;

use Symfony\Component\BrowserKit\Cookie;
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

    public function testLoggedInUserSeesAccountAndLogoutLinks(): void
    {
        $client = static::createClient();
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('account_id', 'test-account');
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a.nav-button[href="/account"]');
        $this->assertSelectorTextContains('a.nav-button[href="/account"]', 'Account');
        $this->assertSelectorTextContains('a.nav-button[href="/auth/logout"]', 'Uitloggen');
        $this->assertStringNotContainsString('Inloggen', $crawler->text());
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
        $this->assertSelectorTextContains('h1', 'De juiste mensen voor publieke opgaven.');
    }

    //About

    public function testShouldDisplayAbout(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/about');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Eén plek voor samenwerking binnen de overheid.');
    }

}
