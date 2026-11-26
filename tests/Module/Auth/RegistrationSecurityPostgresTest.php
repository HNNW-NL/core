<?php

namespace App\Tests\Module\Auth;

use App\Entity\Account\Account;
use App\Tests\Module\Auth\Fixtures\AuthWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class RegistrationSecurityPostgresTest extends AuthWebTestCase
{
    public function testConcurrentRegistrationsCreateOnlyOneCompleteAccount(): void
    {
        self::assertCount(1, array_filter($this->concurrentRequests('register')));
        foreach (['accounts', 'profiles', 'verify_email_tokens', 'statuses'] as $table) {
            self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
        self::assertSame('concurrent@example.test', $this->database->fetchOne('SELECT email FROM accounts'));
    }

    private function register(KernelBrowser $client, array $overrides = []): void
    {
        $page = $client->request('GET', '/auth/register');
        $client->request('POST', '/auth/register', array_replace([
            'full_name' => 'New Person', 'username' => 'new-person', 'email' => 'new.person@example.test',
            'password' => 'Test-password-123', 'confirm_password' => 'Test-password-123', 'terms' => '1',
            '_token' => $page->filter('input[name="_token"]')->attr('value'),
        ], $overrides));
    }

    public function testRegistrationCannotGrantRolesOrVerificationAndEmailConfirmsOnce(): void
    {
        $other = $this->account('other');
        $client = $this->browser();
        $this->register($client, ['email' => ' New.Person@Example.Test ', 'roles' => ['ROLE_ADMIN'], 'is_super_admin' => true,
            'email_verified_at' => '2020-01-01', 'status_id' => 'forged-status', 'account_id' => (string) $other->getId()]);
        self::assertResponseRedirects('/auth/login', 303);
        self::assertCount(1, $this->messages);
        $saved = $this->database->fetchAssociative("SELECT email, email_verified_at, password_hash FROM accounts WHERE username = 'new-person'");
        self::assertSame('new.person@example.test', $saved['email']);
        self::assertNull($saved['email_verified_at']);
        self::assertTrue(password_verify('Test-password-123', $saved['password_hash']));
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['username' => 'new-person']);
        self::assertSame(['ROLE_USER'], $account->getRoles());
        self::assertSame('New Person', $account->getProfile()->getDisplayName());
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM verify_email_tokens'));
        preg_match('~https://trusted\.example\.test/auth/verify-email/([a-f0-9]{64})/[^\s]+~', $this->messages[0]->getTextBody(), $matches);
        self::assertNotEmpty($matches);
        $plain = $matches[1];
        self::assertTrue(password_verify($plain, $this->database->fetchOne('SELECT token_hash FROM verify_email_tokens')));
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Controleer je e-mail');
        $client->request('GET', '/auth/verify-email/'.$plain.'/other@example.test');
        self::assertResponseRedirects('/auth/login', 303);
        self::assertNull($this->database->fetchOne("SELECT email_verified_at FROM accounts WHERE username = 'new-person'"));
        $client->request('GET', '/auth/verify-email/'.$plain.'/new.person@example.test');
        self::assertResponseRedirects('/auth/login', 303);
        self::assertNotNull($this->database->fetchOne("SELECT email_verified_at FROM accounts WHERE username = 'new-person'"));
        $client->request('GET', '/auth/verify-email/'.$plain.'/new.person@example.test');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Ongeldige of verlopen');
    }

    public static function invalidInput(): iterable
    {
        yield 'empty name' => [['full_name' => ''], 'full_name'];
        yield 'long name' => [['full_name' => str_repeat('a', 101)], 'full_name'];
        yield 'short username' => [['username' => 'ab'], 'username'];
        yield 'invalid email' => [['email' => 'invalid'], 'email'];
        yield 'long email' => [['email' => str_repeat('a', 250).'@example.test'], 'email'];
        yield 'short password' => [['password' => 'short', 'confirm_password' => 'short'], 'password'];
        yield 'seven Unicode characters' => [['password' => 'ééééééé', 'confirm_password' => 'ééééééé'], 'password'];
        yield 'oversized password' => [['password' => str_repeat('x', 4097), 'confirm_password' => str_repeat('x', 4097)], 'password'];
        yield 'mismatch' => [['confirm_password' => 'different-password'], 'confirm_password'];
        yield 'terms' => [['terms' => ''], 'terms'];
        yield 'array name' => [['full_name' => ['name']], 'full_name'];
        yield 'array email' => [['email' => ['person@example.test']], 'email'];
        yield 'array password' => [['password' => ['secret']], 'password'];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidRegistrationShowsFieldErrorsAndKeepsPasswordsPrivate(array $input, string $field): void
    {
        $client = $this->browser();
        $this->register($client, $input);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#'.$field.'-error:not(.visually-hidden)');
        self::assertSelectorNotExists('input[type="password"][value]');
        self::assertStringNotContainsString('Test-password-123', $client->getResponse()->getContent());
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM accounts'));
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM profiles'));
        $this->snapshot($client, 'register-error');
    }

    public function testInvalidCsrfCannotCreateAnAccountAndOldValuesAreEscaped(): void
    {
        $client = $this->browser();
        $this->register($client, ['_token' => ['bad'], 'full_name' => '<script>alert(1)</script>']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#register-errors', 'Het formulier is verlopen');
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM accounts'));
    }

    public function testRepeatedRegistrationAndLetterCaseNeverCreateASecondAccount(): void
    {
        $client = $this->browser();
        $this->register($client);
        self::assertResponseRedirects('/auth/login', 303);
        $this->register($client, ['username' => 'another-name', 'email' => ' NEW.PERSON@EXAMPLE.TEST ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#email-error', 'al in gebruik');
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM accounts'));
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM profiles'));
        self::assertCount(1, $this->messages);
    }

    public function testExistingMixedCaseEmailIsNotDuplicated(): void
    {
        $existing = $this->account('legacy');
        $existing->setEmail('Legacy.Mixed@Example.Test');
        $this->entityManager->flush();
        $client = $this->browser();
        $this->register($client, ['email' => 'legacy.mixed@example.test']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#email-error', 'al in gebruik');
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM accounts'));
        self::assertCount(0, $this->messages);
    }

    public function testMailFailureRollsBackAndTheSameRegistrationCanBeRetried(): void
    {
        $client = $this->browser(mailFails: true);
        $this->register($client);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#register-errors', 'tijdelijk niet mogelijk');
        self::assertStringNotContainsString('Private transport', $client->getResponse()->getContent());
        $logs = json_encode($this->logs->getRecords(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Private transport failure', $logs);
        self::assertStringNotContainsString('Test-password-123', $logs);
        foreach (['accounts', 'profiles', 'verify_email_tokens', 'statuses'] as $table) {
            self::assertSame(0, (int) $this->database->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
        $client = $this->browser();
        $this->register($client);
        self::assertResponseRedirects('/auth/login', 303);
        self::assertSame(1, (int) $this->database->fetchOne('SELECT COUNT(*) FROM accounts'));
        self::assertCount(1, $this->messages);
    }
}
