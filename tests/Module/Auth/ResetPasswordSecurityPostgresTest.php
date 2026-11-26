<?php

namespace App\Tests\Module\Auth;

use App\Entity\Account\Account;
use App\Entity\Auth\ResetPasswordToken;
use App\Tests\Module\Auth\Fixtures\AuthWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ResetPasswordSecurityPostgresTest extends AuthWebTestCase
{
    public function testTwoConcurrentResetsAllowExactlyOnePasswordChange(): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $this->resetToken($account);
        $results = $this->concurrentRequests('reset', $plain);
        self::assertCount(1, array_filter($results));
        $winner = array_search(true, $results, true) + 1;
        self::assertTrue(password_verify('Concurrent-password-'.$winner, $this->database->fetchOne('SELECT password_hash FROM accounts')));
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM reset_password_tokens WHERE is_used = false'));
    }

    private function resetPath(string $plain, string $email = 'reset-user@example.test'): string
    {
        return '/auth/reset-password/'.$plain.'/'.rawurlencode($email);
    }

    public function testSuccessfulResetInvalidatesAllLinksAndTwoExistingSessions(): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $this->resetToken($account);
        $client = $this->browser();
        $this->login($client, $account->getEmail());
        $client->followRedirect();
        $firstSession = $client->getCookieJar()->all();
        $client->getCookieJar()->clear();
        $this->login($client, $account->getEmail());
        $client->followRedirect();
        $secondSession = $client->getCookieJar()->all();
        $client->getCookieJar()->clear();
        $page = $client->request('GET', $this->resetPath($plain));
        self::assertResponseIsSuccessful();
        $client->submit($page->selectButton('Wachtwoord opslaan')->form([
            'password' => 'New-password-456', 'confirm_password' => 'New-password-456',
        ]));
        self::assertResponseRedirects('/auth/login', 303);
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM reset_password_tokens WHERE is_used = false'));
        self::assertTrue(password_verify('New-password-456', $this->database->fetchOne('SELECT password_hash FROM accounts')));
        self::assertFalse(password_verify('Test-password-123', $this->database->fetchOne('SELECT password_hash FROM accounts')));
        foreach ([$firstSession, $secondSession] as $cookies) {
            $client->getCookieJar()->clear();
            foreach ($cookies as $cookie) { $client->getCookieJar()->set($cookie); }
            $client->request('GET', '/account');
            self::assertResponseRedirects('/auth/login');
        }
        $client->getCookieJar()->clear();
        $this->login($client, $account->getEmail(), 'Test-password-123');
        self::assertResponseRedirects('/auth/login', 303);
        $this->login($client, $account->getEmail(), 'New-password-456');
        self::assertResponseRedirects('/account', 303);
        $client->request('GET', $this->resetPath($plain));
        self::assertResponseStatusCodeSame(400);
    }

    public function testResetAlsoEndsTheSessionThatSubmitsTheForm(): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $client = $this->browser();
        $this->login($client, $account->getEmail());
        $oldCookies = $client->getCookieJar()->all();
        $page = $client->request('GET', $this->resetPath($plain));
        $client->submit($page->selectButton('Wachtwoord opslaan')->form(['password' => 'New-password-456', 'confirm_password' => 'New-password-456']));
        self::assertResponseRedirects('/auth/login', 303);
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
        foreach ($oldCookies as $cookie) { $client->getCookieJar()->set($cookie); }
        $client->request('GET', '/account');
        self::assertResponseRedirects('/auth/login');
    }

    public static function invalidLinks(): iterable
    {
        foreach (['expired', 'used', 'modified', 'wrong-email', 'unknown-email', 'malformed', 'oversized'] as $state) { yield $state => [$state]; }
    }

    #[DataProvider('invalidLinks')]
    public function testInvalidLinksNeverShowTheFormOrChangeAHash(string $state): void
    {
        $account = $this->account('reset-user');
        $other = $this->account('other-user');
        [$plain, $token] = $this->resetToken($account);
        $email = $account->getEmail();
        if ($state === 'expired') { (new \ReflectionProperty(ResetPasswordToken::class, 'expiresAt'))->setValue($token, new \DateTimeImmutable('-1 minute')); }
        if ($state === 'used') { $token->markAsUsed(); }
        if ($state === 'modified') { $plain = str_repeat('f', 64); }
        if ($state === 'wrong-email') { $email = $other->getEmail(); }
        if ($state === 'unknown-email') { $email = 'unknown@example.test'; }
        if ($state === 'malformed') { $plain = 'invalid'; }
        if ($state === 'oversized') { $plain .= str_repeat('a', 64); }
        $this->entityManager->flush();
        $before = $this->database->fetchFirstColumn('SELECT password_hash FROM accounts ORDER BY id');
        $client = $this->browser();
        foreach (['GET', 'POST'] as $method) {
            $client->request($method, $this->resetPath($plain, $email), ['password' => 'New-password-456', 'confirm_password' => 'New-password-456']);
            self::assertResponseStatusCodeSame(400);
            self::assertSelectorNotExists('input[name="password"]');
            self::assertSelectorExists('a[href="/auth/forgot-password"]');
        }
        self::assertSame($before, $this->database->fetchFirstColumn('SELECT password_hash FROM accounts ORDER BY id'));
        $this->snapshot($client, 'reset-invalid');
    }

    public static function invalidPasswords(): iterable
    {
        yield 'empty' => ['', '', 'password'];
        yield 'short' => ['short', 'short', 'password'];
        yield 'seven Unicode characters' => ['ééééééé', 'ééééééé', 'password'];
        yield 'too long' => [str_repeat('x', 4097), str_repeat('x', 4097), 'password'];
        yield 'mismatch' => ['New-password-456', 'Other-password-789', 'confirm_password'];
        yield 'array' => [['secret'], 'New-password-456', 'password'];
    }

    #[DataProvider('invalidPasswords')]
    public function testInvalidPasswordPreservesHashAndToken(mixed $password, string $confirmation, string $field): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $before = $this->database->fetchOne('SELECT password_hash FROM accounts');
        $client = $this->browser();
        $path = $this->resetPath($plain);
        $page = $client->request('GET', $path);
        $client->request('POST', $path, ['password' => $password, 'confirm_password' => $confirmation,
            '_token' => $page->filter('input[name="_token"]')->attr('value')]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#'.$field.'-error:not(.visually-hidden)');
        self::assertSelectorNotExists('input[type="password"][value]');
        self::assertStringNotContainsString('New-password-456', $client->getResponse()->getContent());
        self::assertSame($before, $this->database->fetchOne('SELECT password_hash FROM accounts'));
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM reset_password_tokens WHERE is_used = false'));
        $this->snapshot($client, 'reset-error');
    }

    public function testInvalidCsrfCannotConsumeALink(): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $client = $this->browser();
        $client->request('POST', $this->resetPath($plain), ['password' => 'New-password-456', 'confirm_password' => 'New-password-456', '_token' => ['bad']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Het formulier is verlopen');
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM reset_password_tokens WHERE is_used = false'));
        self::assertTrue(password_verify('Test-password-123', $this->database->fetchOne('SELECT password_hash FROM accounts')));
    }

    public function testResetPageBlocksExternalAssetsReferrersProfilerAndSensitiveLogs(): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $client = $this->browser();
        $client->enableProfiler();
        $client->request('GET', $this->resetPath($plain).'?_profiler=1', [], [], ['FRANKENPHP_HOT_RELOAD' => 'https://reload.example.test']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString("script-src 'self'", $client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertStringNotContainsString('cdn.jsdelivr.net', $client->getResponse()->getContent());
        self::assertStringNotContainsString('reload.example.test', $client->getResponse()->getContent());
        self::assertFalse($client->getResponse()->headers->has('X-Debug-Token'));
        $serialized = json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($plain, $serialized);
        self::assertStringNotContainsString($account->getEmail(), $serialized);
        $this->snapshot($client, 'reset-form');
    }

    public function testDatabaseFailureRollsBackTheHashAndTokenAndCanBeRetried(): void
    {
        $account = $this->account('reset-user');
        [$plain] = $this->resetToken($account);
        $this->database->executeStatement('ALTER TABLE reset_password_tokens ADD CONSTRAINT prevent_consumption CHECK (is_used = false)');
        $client = $this->browser();
        $path = $this->resetPath($plain);
        $page = $client->request('GET', $path);
        $client->submit($page->selectButton('Wachtwoord opslaan')->form(['password' => 'New-password-456', 'confirm_password' => 'New-password-456']));
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('[role="alert"]', 'tijdelijk niet');
        $logs = json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($plain, $logs);
        self::assertStringNotContainsString($account->getEmail(), $logs);
        self::assertStringNotContainsString('New-password-456', $logs);
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM reset_password_tokens WHERE is_used = false'));
        self::assertTrue(password_verify('Test-password-123', $this->database->fetchOne('SELECT password_hash FROM accounts')));
        $this->database->executeStatement('ALTER TABLE reset_password_tokens DROP CONSTRAINT prevent_consumption');
        $client = $this->browser();
        $page = $client->request('GET', $path);
        $client->submit($page->selectButton('Wachtwoord opslaan')->form(['password' => 'New-password-456', 'confirm_password' => 'New-password-456']));
        self::assertResponseRedirects('/auth/login', 303);
        self::assertTrue(password_verify('New-password-456', $this->database->fetchOne('SELECT password_hash FROM accounts')));
    }
}
