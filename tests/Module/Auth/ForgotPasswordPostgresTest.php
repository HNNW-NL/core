<?php

namespace App\Tests\Module\Auth;

use App\Entity\Account\{Account, AccountSetting, Profile, ProfileExperience};
use App\Entity\Admin\{Admin, AdminRole};
use App\Entity\Auth\ResetPasswordToken;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Kernel;
use App\Module\Auth\Service\{PasswordResetRequestLimiter, PasswordResetService};
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\{Mailer, MailerInterface};
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ForgotPasswordPostgresTest extends WebTestCase
{
    private const EMAIL = 'person@example.test';
    private const CONFIRMATION = 'Als er een account met dit e-mailadres bestaat, ontvang je een e-mail met een herstellink.';
    private const PASSWORD = 'Existing-password-123';
    private ?EntityManager $entityManager = null;
    private ?Connection $database = null;
    private Configuration $configuration;
    private string $schema;
    private bool $schemaCreated = false;
    private Account $account;
    /** @var list<Email> */
    private array $messages = [];
    private TestHandler $logHandler;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $databaseUrl = getenv('AUTH_TEST_DATABASE_URL');
        if (!$databaseUrl) {
            self::markTestSkipped('Set AUTH_TEST_DATABASE_URL to test in an isolated local PostgreSQL schema.');
        }
        $parameters = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($databaseUrl);
        if (($parameters['driver'] ?? null) !== 'pdo_pgsql' || !in_array($parameters['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \InvalidArgumentException('These tests require local PostgreSQL.');
        }

        // Existing Status mapping uses Table::uniqueConstraints. Entity changes belong to the PO.
        \Doctrine\Deprecations\Deprecation::ignoreDeprecations('https://github.com/doctrine/orm/pull/11357');
        if (!Type::hasType(UlidType::NAME)) {
            Type::addType(UlidType::NAME, UlidType::class);
        }
        $configuration = ORMSetup::createAttributeMetadataConfig([__DIR__.'/../../../src/Entity']);
        $configuration->enableNativeLazyObjects(true);
        $configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $this->configuration = $configuration;
        $this->database = DriverManager::getConnection($parameters);
        $this->entityManager = new EntityManager($this->database, $configuration);
        $this->schema = 'auth_forgot_test_'.bin2hex(random_bytes(8));
        $database = $this->entityManager->getConnection();
        $database->executeStatement('CREATE SCHEMA '.$this->schema);
        $this->schemaCreated = true;
        $database->executeStatement('SET search_path TO '.$this->schema);
        (new SchemaTool($this->entityManager))->createSchema(array_map($this->entityManager->getClassMetadata(...), [
            Status::class, Account::class, Profile::class, ProfileExperience::class, AccountSetting::class,
            AdminRole::class, Admin::class, Organisation::class, ResetPasswordToken::class,
        ]));

        $status = (new Status())->setName('active')->setScope('account')->setColourHex('#10B981');
        $this->account = (new Account())->setUsername('recovery-test')->setEmail(self::EMAIL)->setStatus($status)
            ->setPasswordHash(password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]));
        $this->entityManager->persist($status);
        $this->entityManager->persist($this->account);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database !== null) {
                $database = $this->database;
                while ($database->isTransactionActive()) {
                    $database->rollBack();
                }
                if ($this->schemaCreated) {
                    $database->executeStatement('DROP SCHEMA '.$this->schema.' CASCADE');
                }
                $database->close();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testPublicFormDoesNotCreateTokensOnGet(): void
    {
        $client = $this->browser();
        $client->request('GET', '/auth/forgot-password');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_token"]');
        self::assertSelectorExists('label[for="email"]');
        self::assertSelectorExists('a[href="/auth/login"]');
        self::assertSelectorTextSame('button[type="submit"]', 'Herstellink aanvragen');
        self::assertSame(0, $this->tokenCount());
        self::assertCount(0, $this->messages);
        $this->snapshot($client, 'form');
    }

    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid' => ['invalid'];
        yield 'too long' => [str_repeat('a', 250).'@example.test'];
        yield 'markup' => ['<script>alert(1)</script>'];
        yield 'array' => [[self::EMAIL]];
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmailHasAFieldErrorAndNoSideEffects(mixed $email): void
    {
        $client = $this->browser();
        $csrfToken = $this->csrfToken($client);
        $client->request('POST', '/auth/forgot-password', ['_token' => $csrfToken, 'email' => $email]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#email-error', 'Voer een geldig e-mailadres in.');
        self::assertSelectorExists('#email[aria-invalid="true"]');
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        self::assertCount(0, $this->messages);
        self::assertSame(0, $this->tokenCount());
        $this->snapshot($client, 'invalid');
    }

    public static function invalidCsrfTokens(): iterable
    {
        yield 'missing' => [null];
        yield 'wrong' => ['invalid'];
        yield 'array' => [['invalid']];
    }

    #[DataProvider('invalidCsrfTokens')]
    public function testInvalidCsrfPreservesEmailAndDoesNotCreateAMail(mixed $csrfToken): void
    {
        $client = $this->browser();
        $client->request('POST', '/auth/forgot-password', ['_token' => $csrfToken, 'email' => self::EMAIL]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#forgot-errors', 'Het formulier is verlopen.');
        self::assertSelectorExists('input[name="email"][value="'.self::EMAIL.'"]');
        self::assertCount(0, $this->messages);
        self::assertSame(0, $this->tokenCount());
    }

    public static function accountStates(): iterable
    {
        yield 'known' => [self::EMAIL, false, 1];
        yield 'unknown' => ['unknown@example.test', false, 0];
        yield 'deleted' => [self::EMAIL, true, 0];
    }

    #[DataProvider('accountStates')]
    public function testKnownUnknownAndDeletedAccountsHaveTheSameConfirmation(string $email, bool $deleted, int $mailCount): void
    {
        if ($deleted) {
            $this->account->softDelete();
            $this->entityManager->flush();
        }
        $client = $this->browser();
        $this->requestLink($client, $email);
        self::assertResponseRedirects('/auth/forgot-password', 303);
        $client->followRedirect();
        self::assertSelectorTextSame('[role="status"]', self::CONFIRMATION);
        self::assertCount($mailCount, $this->messages);
        self::assertSame($mailCount, $this->tokenCount());
        $this->snapshot($client, 'confirmation');
    }

    public function testMailedLinkUsesTrustedBaseAndWorksForAnExistingMixedCaseEmail(): void
    {
        $this->account->setEmail('Person@Example.Test');
        $this->entityManager->flush();
        $client = $this->browser();
        $page = $client->request('GET', 'https://untrusted.example/auth/forgot-password');
        $originalContext = clone self::getContainer()->get(UrlGeneratorInterface::class)->getContext();
        $originalContext->setMethod('POST');
        $client->submit($page->selectButton('Herstellink aanvragen')->form(['email' => '  PERSON@example.test  ']));
        self::assertResponseRedirects('/auth/forgot-password', 303);
        self::assertCount(1, $this->messages);
        $mail = $this->messages[0];
        $link = $this->mailedLink($mail);
        self::assertStringStartsWith('https://trusted.example.test/auth/reset-password/', $link);
        self::assertStringNotContainsString('untrusted.example', $link);
        self::assertSame('Person@Example.Test', $mail->getTo()[0]->getAddress());
        self::assertEquals($originalContext, self::getContainer()->get(UrlGeneratorInterface::class)->getContext());
        $stored = $this->entityManager->getRepository(ResetPasswordToken::class)->findOneBy([]);
        $plainToken = explode('/', parse_url($link, PHP_URL_PATH))[3];
        self::assertSame(64, strlen($plainToken));
        self::assertNotSame($plainToken, $stored->getTokenHash());
        self::assertTrue(password_verify($plainToken, $stored->getTokenHash()));
        self::assertLessThanOrEqual(3600, $stored->getExpiresAt()->getTimestamp() - $stored->getCreatedAt()->getTimestamp());
        $client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="password"]');
    }

    public function testNewRequestReplacesOldLinksAndNewLinkCanResetOnce(): void
    {
        $client = $this->browser();
        $this->requestLink($client);
        $oldLink = $this->mailedLink($this->messages[0]);
        $this->requestLink($client);
        $newLink = $this->mailedLink($this->messages[1]);
        self::assertNotSame($oldLink, $newLink);
        self::assertSame(2, $this->tokenCount());
        self::assertSame(1, $this->tokenCount(unusedOnly: true));
        $client->request('GET', $oldLink);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorExists('a[href="/auth/forgot-password"]');
        self::assertTrue(password_verify(self::PASSWORD, $this->storedPassword()));
        $page = $client->request('GET', $newLink);
        self::assertResponseIsSuccessful();
        $client->submit($page->selectButton('Wachtwoord opslaan')->form([
            'password' => 'New-password-456', 'confirm_password' => 'New-password-456',
        ]));
        self::assertResponseRedirects('/auth/login', 303);
        self::assertTrue(password_verify('New-password-456', $this->storedPassword()));
        self::assertFalse(password_verify(self::PASSWORD, $this->storedPassword()));
        self::assertSame(0, $this->tokenCount(unusedOnly: true));
        $client->request('GET', $newLink);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorExists('a[href="/auth/forgot-password"]');
    }

    public function testExpiredLinkIsRejectedWithoutChangingPassword(): void
    {
        $client = $this->browser();
        $this->requestLink($client);
        $token = $this->entityManager->getRepository(ResetPasswordToken::class)->findOneBy([]);
        (new \ReflectionProperty(ResetPasswordToken::class, 'expiresAt'))->setValue($token, new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();
        $client->request('GET', $this->mailedLink($this->messages[0]));
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorExists('a[href="/auth/forgot-password"]');
        self::assertTrue(password_verify(self::PASSWORD, $this->storedPassword()));
    }

    public static function limitedEmails(): iterable
    {
        yield 'known' => [self::EMAIL, 5];
        yield 'unknown' => ['unknown@example.test', 0];
    }

    #[DataProvider('limitedEmails')]
    public function testSixthRequestIsLimitedForKnownAndUnknownEmails(string $email, int $mailCount): void
    {
        $client = $this->browser();
        $csrfToken = $this->csrfToken($client);
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $client->request('POST', '/auth/forgot-password', ['_token' => $csrfToken, 'email' => $email]);
            self::assertResponseRedirects('/auth/forgot-password', 303);
        }
        $client->request('POST', '/auth/forgot-password', ['_token' => $csrfToken, 'email' => $email]);
        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('#forgot-errors', 'te vaak');
        self::assertSelectorNotExists('[role="status"]');
        self::assertGreaterThan(0, (int) $client->getResponse()->headers->get('Retry-After'));
        self::assertLessThanOrEqual(901, (int) $client->getResponse()->headers->get('Retry-After'));
        self::assertCount($mailCount, $this->messages);
        self::assertSame($mailCount, $this->tokenCount());
        $this->snapshot($client, 'limited');
    }

    public function testInvalidCsrfDoesNotUseTheValidRequestAllowance(): void
    {
        $client = $this->browser();
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $client->request('POST', '/auth/forgot-password', ['email' => self::EMAIL]);
            self::assertResponseStatusCodeSame(422);
        }
        $this->requestLink($client);
        self::assertResponseRedirects('/auth/forgot-password', 303);
        self::assertCount(1, $this->messages);
    }

    public function testMailFailureRollsBackReplacementAndAllowsRetryWithoutLoggingTheUrl(): void
    {
        $previous = (new ResetPasswordToken())->setAccount($this->account)->setTokenHash(password_hash('previous-link', PASSWORD_BCRYPT, ['cost' => 4]));
        $this->entityManager->persist($previous);
        $this->entityManager->flush();
        $client = $this->browser(mailFails: true);
        $this->requestLink($client);
        self::assertResponseRedirects('/auth/forgot-password', 303);
        $client->followRedirect();
        self::assertSelectorTextSame('[role="status"]', self::CONFIRMATION);
        self::assertSame(1, $this->tokenCount());
        self::assertSame(1, $this->tokenCount(unusedOnly: true));
        self::assertCount(0, $this->messages);
        $errors = array_values(array_filter($this->logHandler->getRecords(), static fn ($record) => $record->message === 'Password reset request could not be completed.'));
        self::assertCount(1, $errors);
        self::assertSame(['exception_class' => TransportException::class], $errors[0]->context);

        // A later HTTP request receives a new entity manager after the rolled-back transaction.
        $this->entityManager = new EntityManager($this->database, $this->configuration);
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $mail): void { $this->messages[] = $mail; });
        $service = new PasswordResetService($this->entityManager, $mailer, self::getContainer()->get(UrlGeneratorInterface::class), 'https://trusted.example.test', 'no-reply@example.test', self::getContainer()->get('security.user_password_hasher'));
        $service->sendResetLink(self::EMAIL);
        self::assertCount(1, $this->messages);
        self::assertSame(2, $this->tokenCount());
        self::assertSame(1, $this->tokenCount(unusedOnly: true));
    }

    public function testDatabaseFailureDoesNotReportAccountExistenceOrSendMail(): void
    {
        $client = $this->browser();
        $this->entityManager->getConnection()->executeStatement('ALTER TABLE reset_password_tokens ADD CONSTRAINT reject_new_tokens CHECK (is_used = true)');
        $this->requestLink($client);
        self::assertResponseRedirects('/auth/forgot-password', 303);
        $client->followRedirect();
        self::assertSelectorTextSame('[role="status"]', self::CONFIRMATION);
        self::assertSame(0, $this->tokenCount());
        self::assertCount(0, $this->messages);
    }

    public function testUnavailableLimiterShowsARecoverableErrorWithoutSendingMail(): void
    {
        $client = $this->browser(limiterFails: true);
        $this->requestLink($client);
        self::assertResponseStatusCodeSame(503);
        self::assertSelectorTextContains('#forgot-errors', 'tijdelijk niet mogelijk');
        self::assertStringNotContainsString('Private cache', $client->getResponse()->getContent());
        self::assertSame(0, $this->tokenCount());
        self::assertCount(0, $this->messages);
    }

    public function testPayloadCannotChooseTheTokenOrItsAccount(): void
    {
        $client = $this->browser();
        $client->request('POST', '/auth/forgot-password', [
            '_token' => $this->csrfToken($client),
            'email' => self::EMAIL,
            'account_id' => (string) new \Symfony\Component\Uid\Ulid(),
            'token' => 'attacker-chosen-token',
            'token_hash' => 'attacker-chosen-hash',
            'is_used' => true,
            'expires_at' => '2099-01-01',
        ]);
        self::assertResponseRedirects('/auth/forgot-password', 303);
        self::assertCount(1, $this->messages);
        $token = $this->entityManager->getRepository(ResetPasswordToken::class)->findOneBy([]);
        self::assertEquals($this->account->getId(), $token->getAccount()->getId());
        self::assertFalse($token->isUsed());
        self::assertFalse(password_verify('attacker-chosen-token', $token->getTokenHash()));
        self::assertLessThanOrEqual(3600, $token->getExpiresAt()->getTimestamp() - $token->getCreatedAt()->getTimestamp());
    }

    private function browser(bool $mailFails = false, bool $limiterFails = false): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->catchExceptions(false);
        $container = self::getContainer();
        $container->set('doctrine.orm.default_entity_manager', $this->entityManager);
        $this->logHandler = new TestHandler();
        $container->get('logger')->pushHandler($this->logHandler);
        $nullMailer = new Mailer(new NullTransport($container->get('event_dispatcher')));
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $mail) use ($mailFails, $nullMailer): void {
            if ($mailFails) {
                throw new TransportException('Delivery failed for '.$mail->getTextBody());
            }
            $nullMailer->send($mail);
            $this->messages[] = $mail;
        });
        $container->set(PasswordResetService::class, new PasswordResetService(
            $this->entityManager, $mailer, $container->get(UrlGeneratorInterface::class),
            'https://trusted.example.test', 'HNNW <no-reply@example.test>', $container->get('security.user_password_hasher'),
        ));
        $storage = new InMemoryStorage();
        $configuration = [
            'policy' => 'sliding_window',
            'limit' => $container->getParameter('auth.password_reset.limit'),
            'interval' => $container->getParameter('auth.password_reset.interval'),
        ];
        $ipFactory = new RateLimiterFactory(['id' => 'test-ip'] + $configuration, $storage);
        if ($limiterFails) {
            $ipFactory = $this->createStub(RateLimiterFactoryInterface::class);
            $ipFactory->method('create')->willThrowException(new \RuntimeException('Private cache connection details'));
        }
        $container->set(PasswordResetRequestLimiter::class, new PasswordResetRequestLimiter(
            $ipFactory,
            new RateLimiterFactory(['id' => 'test-email'] + $configuration, $storage),
            'test-secret',
        ));

        return $client;
    }

    private function csrfToken(KernelBrowser $client): string
    {
        return $client->request('GET', '/auth/forgot-password')->filter('input[name="_token"]')->attr('value');
    }

    private function requestLink(KernelBrowser $client, string $email = self::EMAIL): void
    {
        $csrfToken = $this->csrfToken($client);
        $client->request('POST', '/auth/forgot-password', ['_token' => $csrfToken, 'email' => $email]);
    }

    private function mailedLink(Email $mail): string
    {
        preg_match('~https://[^\s]+~', $mail->getTextBody(), $matches);
        self::assertNotEmpty($matches);

        return $matches[0];
    }

    private function tokenCount(bool $unusedOnly = false): int
    {
        return (int) $this->database->fetchOne('SELECT COUNT(*) FROM reset_password_tokens'.($unusedOnly ? ' WHERE is_used = false' : ''));
    }

    private function storedPassword(): string
    {
        return $this->database->fetchOne('SELECT password_hash FROM accounts');
    }

    private function snapshot(KernelBrowser $client, string $name): void
    {
        if ($directory = getenv('AUTH_SCREENSHOT_DIR')) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/'.$name.'.html', $client->getResponse()->getContent());
        }
    }
}
