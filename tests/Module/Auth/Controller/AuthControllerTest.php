<?php

namespace App\Tests\Module\Auth\Controller;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AuthControllerTest extends WebTestCase
{
    // symfony's stateless csrf (config/packages/csrf.yaml) wil bij een post een Origin- of Referer-header van de site zelf; na een eerder request stuurt de testclient zelf een Referer mee, maar bij een post als allereerste request niet, daarom geven we de Origin mee
    private const ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    // login

    public function testShouldDisplayLoginPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auth/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form#login-form input[name="username"]');
        $this->assertSelectorExists('form#login-form input[name="password"]');
        $this->assertSelectorExists('form#login-form input[name="_csrf_token"]');
    }

    public function testWhenPasswordIsCorrectShouldLogInAndOpenAccount(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/auth/login');

        $this->submitLoginForm($client, $crawler, 'TestUser', 'test1234');

        $this->assertResponseRedirects();

        $client->request('GET', '/account');
        $this->assertResponseIsSuccessful();
    }

    public function testWhenLoggingInWithEmailShouldLogIn(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/auth/login');

        // de provider in security.yaml zoekt eerst op e-mail en dan op gebruikersnaam
        $this->submitLoginForm($client, $crawler, 'Test@test.nl', 'test1234');

        $this->assertResponseRedirects();

        $client->request('GET', '/account');
        $this->assertResponseIsSuccessful();
    }

    public function testWhenPasswordIsWrongShouldShowErrorAndStayLoggedOut(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/auth/login');

        $this->submitLoginForm($client, $crawler, 'TestUser', 'verkeerd');

        $this->assertResponseRedirects('/auth/login');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.form-errors', 'Ongeldige gebruikersnaam/e-mail of wachtwoord.');

        $client->request('GET', '/account');
        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenCsrfTokenIsMissingShouldNotLogIn(): void
    {
        $client = static::createClient();

        // de velden los posten zonder _csrf_token, door enable_csrf in security.yaml mag dit niet
        $client->request('POST', '/auth/login', [
            'username' => 'TestUser',
            'password' => 'test1234',
        ], [], self::ORIGIN);

        $this->assertResponseRedirects('/auth/login');

        $client->request('GET', '/account');
        $this->assertResponseRedirects('/auth/login');
    }

    public function testWhenLoggedInShouldFillLastLoginAt(): void
    {
        $client = static::createClient();

        // de fixture heeft nog nooit ingelogd, dus de datum is leeg
        $this->assertNull($this->findAccount('Test@test.nl')->getLastLoginAt());

        $crawler = $client->request('GET', '/auth/login');
        $this->submitLoginForm($client, $crawler, 'TestUser', 'test1234');

        $this->assertResponseRedirects();

        // de LoginSuccessListener zet de datum na een gelukte login
        $this->assertNotNull($this->findAccount('Test@test.nl')->getLastLoginAt());
    }

    // logout

    public function testWhenLoggedOutShouldRedirectFromAccountAgain(): void
    {
        $client = static::createClient();
        $client->loginUser($this->findAccount('Test@test.nl'));

        $client->request('GET', '/account');
        $this->assertResponseIsSuccessful();

        // de route /auth/logout maakt symfony zelf aan door de logout-instelling in security.yaml
        $client->request('GET', '/auth/logout');
        $this->assertResponseRedirects();

        $client->request('GET', '/account');
        $this->assertResponseRedirects('/auth/login');
    }

    // hulpfuncties

    // haalt een account uit de testdatabase, altijd via de container van dit moment omdat de kernel tussen requests opnieuw start
    private function findAccount(string $email): Account
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em->getRepository(Account::class)->findOneBy(['email' => $email]);
    }

    // vult het echte login-formulier in (met de verborgen _csrf_token) en verstuurt het zoals een browser dat doet
    private function submitLoginForm(KernelBrowser $client, Crawler $crawler, string $username, string $password): void
    {
        $form = $crawler->selectButton('Log in')->form([
            'username' => $username,
            'password' => $password,
        ]);

        $client->submit($form, [], self::ORIGIN);
    }
}
