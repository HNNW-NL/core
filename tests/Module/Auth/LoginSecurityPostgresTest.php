<?php

namespace App\Tests\Module\Auth;

use App\Entity\Account\Account;
use App\Tests\Module\Auth\Fixtures\AuthWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class LoginSecurityPostgresTest extends AuthWebTestCase
{
    public function testValidLoginMigratesSessionAndLoadsTheSameAccount(): void
    {
        $account = $this->account('login-user');
        $client = $this->browser();
        $client->request('GET', '/auth/login');
        $oldSessionId = $client->getRequest()->getSession()->getId();
        $this->login($client, strtoupper($account->getEmail()));
        self::assertResponseRedirects('/account', 303);
        self::assertNotSame($oldSessionId, $client->getRequest()->getSession()->getId());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#account-name', 'login-user display');
        self::assertSelectorTextContains('#email-status', 'bevestigd');
        self::assertNotNull($this->database->fetchOne('SELECT last_login_at FROM accounts'));
        $this->snapshot($client, 'account-empty');
    }

    public static function invalidCredentials(): iterable
    {
        yield 'wrong password' => ['known@example.test', 'incorrect'];
        yield 'unknown email' => ['unknown@example.test', 'Test-password-123'];
        yield 'empty' => ['', ''];
        yield 'array username' => [['known@example.test'], 'Test-password-123'];
        yield 'array password' => ['known@example.test', ['password']];
        yield 'oversized password' => ['known@example.test', str_repeat('x', 4097)];
    }

    #[DataProvider('invalidCredentials')]
    public function testInvalidLoginNeverGrantsAccessOrEchoesPassword(mixed $identifier, mixed $password): void
    {
        $this->account('known');
        $client = $this->browser();
        $page = $client->request('GET', '/auth/login');
        $client->request('POST', '/auth/login', ['username' => $identifier, 'password' => $password, '_csrf_token' => $page->filter('input[name="_csrf_token"]')->attr('value')]);
        self::assertResponseRedirects('/auth/login', 303);
        $client->followRedirect();
        self::assertSelectorTextSame('[role="alert"]', 'Ongeldige gebruikersnaam/e-mail of wachtwoord.');
        self::assertSelectorNotExists('input[type="password"][value]');
        self::assertNull($this->database->fetchOne('SELECT last_login_at FROM accounts'));
        $this->snapshot($client, 'login-error');
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }

    public function testMissingLoginCsrfCannotAuthenticate(): void
    {
        $this->account('csrf-user');
        $client = $this->browser();
        $client->request('POST', '/auth/login', ['username' => 'csrf-user@example.test', 'password' => 'Test-password-123']);
        self::assertResponseRedirects('/auth/login', 303);
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }

    public static function blockedStates(): iterable
    {
        foreach (['blocked', 'disabled', 'suspended'] as $state) { yield $state => [$state]; }
        yield 'deleted' => ['deleted'];
    }

    #[DataProvider('blockedStates')]
    public function testBlockedAccountsCannotLogin(string $state): void
    {
        $account = $this->account('blocked-user', state: $state === 'deleted' ? 'active' : $state);
        if ($state === 'deleted') { $account->softDelete(); $this->entityManager->flush(); }
        $client = $this->browser();
        $this->login($client, 'blocked-user@example.test');
        self::assertResponseRedirects('/auth/login', 303);
        $client->followRedirect();
        self::assertSelectorTextSame('[role="alert"]', 'Ongeldige gebruikersnaam/e-mail of wachtwoord.');
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }

    public function testBlockingAnAccountRevokesItsExistingSession(): void
    {
        $account = $this->account('active-session');
        $client = $this->browser();
        $this->login($client, $account->getEmail());
        self::assertResponseRedirects('/account', 303);
        $this->entityManager->find(Account::class, $account->getId())->setStatus($this->fixtureStatus('account', 'blocked'));
        $this->entityManager->flush();
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }

    public function testSixthAttemptCannotLoginEvenWithTheCorrectPassword(): void
    {
        $this->account('limited-login');
        $client = $this->browser();
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->login($client, 'limited-login@example.test', 'wrong');
            self::assertResponseRedirects('/auth/login', 303);
        }
        $this->login($client, 'limited-login@example.test');
        self::assertResponseRedirects('/auth/login', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'te vaak');
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }

    public function testLoginDatabaseFailureShowsAControlledErrorAndCanBeRetried(): void
    {
        $account = $this->account('database-login');
        $client = $this->browser();
        $page = $client->request('GET', '/auth/login');
        $this->database->executeStatement('ALTER TABLE accounts RENAME TO accounts_unavailable');
        $client->request('POST', '/auth/login', ['username' => $account->getEmail(), 'password' => 'Test-password-123',
            '_csrf_token' => $page->filter('input[name="_csrf_token"]')->attr('value')]);
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[role="alert"]', 'tijdelijk niet');
        self::assertStringNotContainsString('SQLSTATE', $client->getResponse()->getContent());
        self::assertStringNotContainsString('Test-password-123', $client->getResponse()->getContent());
        $this->database->executeStatement('ALTER TABLE accounts_unavailable RENAME TO accounts');
        self::assertNull($this->database->fetchOne('SELECT last_login_at FROM accounts'));
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
        $this->login($client, $account->getEmail());
        self::assertResponseRedirects('/account', 303);
    }

    public static function externalTargets(): iterable
    {
        foreach (['https://evil.example', '//evil.example', '/\\evil.example', 'javascript:alert(1)', '%2f%2fevil.example'] as $url) { yield $url => [$url]; }
    }

    #[DataProvider('externalTargets')]
    public function testExternalReturnUrlsAreIgnored(string $url): void
    {
        $this->account('safe-target');
        $client = $this->browser();
        $this->login($client, 'safe-target@example.test', extra: ['_target_path' => $url, 'return' => $url, 'redirect' => $url]);
        self::assertResponseRedirects('/account', 303);
    }

    public function testUnverifiedAccountCannotApplyAndLoginDoesNotGrantProjectRights(): void
    {
        $owner = $this->account('project-owner');
        $unverified = $this->account('unverified', verified: false);
        $project = $this->project($owner, 'Published project', 'public');
        $client = $this->browser();
        $this->login($client, $unverified->getEmail());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#email-status', 'nog niet bevestigd');
        $client->request('GET', '/projects/'.$project->getSlug().'/apply');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('[role="alert"]', 'bevestigingslink');
        $client->catchExceptions(true);
        $client->request('POST', '/projects/'.$project->getSlug().'/apply', ['profile_id' => (string) $owner->getProfile()->getId()]);
        self::assertResponseStatusCodeSame(405);
        $client->request('GET', '/projects/'.$project->getSlug().'/participants');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM project_applications'));
    }

    public function testLogoutRequiresPostAndCsrfAndOldCookieCannotAccessAccount(): void
    {
        $this->account('logout-user');
        $client = $this->browser();
        $this->login($client, 'logout-user@example.test');
        $page = $client->followRedirect();
        $logoutCsrf = $page->filter('form[action="/auth/logout"] input[name="_csrf_token"]')->attr('value');
        $cookies = $client->getCookieJar()->all();
        $client->catchExceptions(true);
        $client->request('GET', '/auth/logout');
        self::assertResponseStatusCodeSame(405);
        $client->request('POST', '/auth/logout');
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/auth/logout', ['_csrf_token' => $logoutCsrf]);
        self::assertResponseRedirects('/auth/login');
        foreach ($cookies as $cookie) { $client->getCookieJar()->set($cookie); }
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }
}
