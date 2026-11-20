<?php

namespace App\Tests\Module\Auth;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Profile;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Auth\ResetPasswordToken;
use App\Entity\Common\Status;
use App\Module\Auth\Service\PasswordResetService;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class PasswordResetPostgresTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';
    private ?EntityManager $em = null;
    private string $schema;
    private bool $schemaCreated = false;
    private Account $account;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        if (!getenv('PASSWORD_RESET_TEST_DATABASE_URL')) {
            self::markTestSkipped('Set PASSWORD_RESET_TEST_DATABASE_URL to use an isolated local PostgreSQL schema.');
        }
        $this->schema = 'password_reset_test_'.bin2hex(random_bytes(8));
        $this->em = self::connect();
        $db = $this->em->getConnection();
        $db->executeStatement('CREATE SCHEMA '.$this->schema);
        $this->schemaCreated = true;
        $db->executeStatement('SET search_path TO '.$this->schema);
        (new SchemaTool($this->em))->createSchema(array_map($this->em->getClassMetadata(...), [
            Status::class, Account::class, Profile::class, AccountSetting::class,
            AdminRole::class, Admin::class, ResetPasswordToken::class,
        ]));
        $this->hasher = self::passwordHasher();
        $status = (new Status())->setName('active')->setScope('account')->setColourHex('#10B981');
        $this->account = (new Account())->setUsername('password-reset-test')->setEmail('reset@example.test')->setStatus($status);
        $this->account->setPasswordHash($this->hasher->hashPassword($this->account, 'Old-password-123'));
        $this->em->persist($status);
        $this->em->persist($this->account);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if ($this->em === null) {
            return;
        }
        $db = $this->em->getConnection();
        try {
            while ($db->isTransactionActive()) {
                $db->rollBack();
            }
            if ($this->schemaCreated) {
                $db->executeStatement('DROP SCHEMA '.$this->schema.' CASCADE');
            }
        } finally {
            $db->close();
        }
    }

    public function testResetPersistsHashAndInvalidatesAllAccountLinks(): void
    {
        $plain = $this->token();
        $older = $this->token();
        $service = $this->service();
        self::assertTrue($service->resetPassword($plain, 'reset@example.test', 'New-password-456'));
        $this->em->clear();

        $account = $this->em->find(Account::class, $this->account->getId());
        self::assertTrue($this->hasher->isPasswordValid($account, 'New-password-456'));
        self::assertFalse($this->hasher->isPasswordValid($account, 'Old-password-123'));
        self::assertFalse($service->resetPassword($plain, 'reset@example.test', 'Replay-password-789'));
        self::assertNull($service->findValidToken($older, 'reset@example.test'));
        self::assertSame(0, $this->em->getRepository(ResetPasswordToken::class)->count(['isUsed' => false]));
    }

    public function testNewRequestReplacesOldLinkAndStoresOnlyHash(): void
    {
        $older = $this->token();
        $mail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function ($message) use (&$mail): void {
            $mail = $message;
        });
        $service = $this->service($mailer);
        $service->sendResetLink('reset@example.test');
        $this->em->clear();

        self::assertNull($service->findValidToken($older, 'reset@example.test'));
        $link = $mail->getContext()['reset_link'];
        preg_match('~/reset-password/([0-9a-f]{64})/~', $link, $matches);
        $token = $service->findValidToken($matches[1], 'reset@example.test');
        self::assertNotNull($token);
        self::assertNotSame($matches[1], $token->getTokenHash());
        self::assertTrue(password_verify($matches[1], $token->getTokenHash()));
        self::assertSame(1, $this->em->getRepository(ResetPasswordToken::class)->count(['isUsed' => false]));
    }

    public function testFailedTokenUpdateAlsoRollsBackPasswordChange(): void
    {
        $plain = $this->token();
        $db = $this->em->getConnection();
        $oldHash = $this->account->getPasswordHash();
        $db->executeStatement('ALTER TABLE reset_password_tokens ADD CONSTRAINT deny_consumption CHECK (is_used = false)');
        try {
            $this->service()->resetPassword($plain, 'reset@example.test', 'New-password-456');
            self::fail('Expected the token update to fail.');
        } catch (DriverException $exception) {
            self::assertSame('23514', $exception->getSQLState());
            self::assertSame($oldHash, $db->fetchOne('SELECT password_hash FROM accounts'));
            self::assertSame(0, (int) $db->fetchOne('SELECT is_used::int FROM reset_password_tokens'));
        }
    }

    public function testMailFailureRollsBackNewTokenAndPreservesPreviousLink(): void
    {
        $plain = $this->token();
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Simulated mail failure'));
        try {
            $this->service($mailer)->sendResetLink('reset@example.test');
            self::fail('Expected mail delivery to fail.');
        } catch (TransportException) {
            $db = $this->em->getConnection();
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM reset_password_tokens'));
            self::assertSame(0, (int) $db->fetchOne('SELECT is_used::int FROM reset_password_tokens'));
            self::assertTrue(password_verify($plain, $db->fetchOne('SELECT token_hash FROM reset_password_tokens')));
        }
    }

    public function testDeletedAccountCannotReceiveOrRedeemLink(): void
    {
        $plain = $this->token();
        $this->account->softDelete();
        $this->em->flush();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        $service = $this->service($mailer);
        $service->sendResetLink('reset@example.test');
        self::assertFalse($service->resetPassword($plain, 'reset@example.test', 'New-password-456'));
        self::assertTrue($this->hasher->isPasswordValid($this->account, 'Old-password-123'));
    }

    public function testConcurrentSubmissionsAllowExactlyOneReset(): void
    {
        $plain = $this->token();
        $db = $this->em->getConnection();
        $processes = [];
        $ready = [false, false];
        $db->beginTransaction();
        $db->executeQuery('SELECT id FROM accounts WHERE id = ? FOR UPDATE', [(string) $this->account->getId()])->fetchOne();
        try {
            foreach (['First-password-123', 'Second-password-456'] as $index => $password) {
                $process = new Process([PHP_BINARY, __DIR__.'/Fixtures/reset-password-worker.php', $this->schema, $plain, $password], self::ROOT, [
                    'PASSWORD_RESET_TEST_DATABASE_URL' => getenv('PASSWORD_RESET_TEST_DATABASE_URL'),
                ]);
                $process->setTimeout(30);
                $process->start(static function (string $type, string $output) use (&$ready, $index): void {
                    $ready[$index] = $ready[$index] || str_contains($output, 'ready');
                });
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (in_array(false, $ready, true) && microtime(true) < $deadline) {
                foreach ($processes as $process) {
                    $process->isRunning(); // Pump output callbacks for both workers.
                }
                usleep(10000);
            }
            foreach ($processes as $index => $process) {
                self::assertTrue($ready[$index], 'Concurrent worker did not reach the transaction: '.$process->getErrorOutput());
            }
            $db->commit();

            $successful = 0;
            foreach ($processes as $process) {
                $process->wait();
                self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $successful += (int) str_contains($process->getOutput(), 'result:1');
            }
            self::assertSame(1, $successful);
            self::assertSame(1, (int) $db->fetchOne('SELECT is_used::int FROM reset_password_tokens'));
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }

    public static function connect(): EntityManager
    {
        // Existing Status metadata uses the deprecated Table::uniqueConstraints
        // option. Its unrelated migration is outside AU01/AU04.
        \Doctrine\Deprecations\Deprecation::ignoreDeprecations('https://github.com/doctrine/orm/pull/11357');
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse(getenv('PASSWORD_RESET_TEST_DATABASE_URL'));
        if (($params['driver'] ?? null) !== 'pdo_pgsql' || !in_array($params['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \InvalidArgumentException('Use a local PostgreSQL instance for password reset integration tests.');
        }
        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }
        $config = ORMSetup::createAttributeMetadataConfig([self::ROOT.'/src/Entity']);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));

        return new EntityManager(DriverManager::getConnection($params), $config);
    }

    public static function passwordHasher(): UserPasswordHasherInterface
    {
        return new UserPasswordHasher(new PasswordHasherFactory([Account::class => new NativePasswordHasher(cost: 4)]));
    }

    private function service(?MailerInterface $mailer = null): PasswordResetService
    {
        $routes = new RouteCollection();
        $routes->add('auth.resetPassword', new Route('/auth/reset-password/{token}/{email}'));

        return new PasswordResetService(
            $this->em, $this->hasher, $mailer ?? $this->createStub(MailerInterface::class),
            new UrlGenerator($routes, new RequestContext()), 'https://hnnw.example.test', 'HNNW <no-reply@example.test>',
        );
    }

    private function token(): string
    {
        $plain = bin2hex(random_bytes(32));
        $token = (new ResetPasswordToken())->setAccount($this->account)->setTokenHash(password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]));
        $this->em->persist($token);
        $this->em->flush();

        return $plain;
    }
}
