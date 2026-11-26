<?php

namespace App\Tests\Module\Auth\Fixtures;

use App\Entity\Account\{Account, AccountSetting, Notification, Profile, ProfileExperience};
use App\Entity\Admin\{Admin, AdminRole};
use App\Entity\Auth\{ResetPasswordToken, VerifyEmailToken};
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\{PackageTask, Project, ProjectApplication, ProjectParticipant, ProjectRole, WorkPackage};
use App\Kernel;
use App\Module\AccountCentre\Service\AccountDashboardService;
use App\Module\Auth\Service\{PasswordResetService, RegistrationService};
use App\Repository\Account\ProfileRepository;
use App\Repository\Project\{ProjectParticipantRepository, ProjectRepository, WorkPackageRepository};
use App\Security\{AccountAuthenticator, AccountUserChecker, AccountUserProvider};
use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\{Configuration, EntityManager, ORMSetup};
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Monolog\Handler\TestHandler;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mailer\{Mailer, MailerInterface};
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Process\{InputStream, Process};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AuthWebTestCase extends WebTestCase
{
    protected ?Connection $database = null;
    protected EntityManager $entityManager;
    protected Configuration $configuration;
    protected string $schema;
    private bool $schemaCreated = false;
    /** @var list<Email> */
    protected array $messages = [];
    protected TestHandler $logs;

    protected static function getKernelClass(): string { return Kernel::class; }

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['APP_SECRET'] = $_ENV['APP_SECRET'] = 'isolated-auth-security-test-secret';
        putenv('APP_SECRET=isolated-auth-security-test-secret');
        $url = getenv('AUTH_TEST_DATABASE_URL');
        if (!$url) {
            self::markTestSkipped('Set AUTH_TEST_DATABASE_URL to test in an isolated local PostgreSQL schema.');
        }
        $parameters = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        if (($parameters['driver'] ?? null) !== 'pdo_pgsql' || !in_array($parameters['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \InvalidArgumentException('Authentication tests require local PostgreSQL.');
        }
        \Doctrine\Deprecations\Deprecation::ignoreDeprecations('https://github.com/doctrine/orm/pull/11357');
        if (!Type::hasType(UlidType::NAME)) { Type::addType(UlidType::NAME, UlidType::class); }
        $this->configuration = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 4).'/src/Entity']);
        $this->configuration->enableNativeLazyObjects(true);
        $this->configuration->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn () => $this->entityManager);
        $factories = [];
        foreach ([ProfileRepository::class, ProjectRepository::class, ProjectParticipantRepository::class, WorkPackageRepository::class] as $repository) {
            $factories[$repository] = static fn () => new $repository($registry);
        }
        $this->configuration->setRepositoryFactory(new ContainerRepositoryFactory(new ServiceLocator($factories)));
        $this->database = DriverManager::getConnection($parameters);
        $this->entityManager = new EntityManager($this->database, $this->configuration);
        $this->schema = 'auth_security_test_'.bin2hex(random_bytes(8));
        $this->database->executeStatement('CREATE SCHEMA '.$this->schema);
        $this->schemaCreated = true;
        $this->database->executeStatement('SET search_path TO '.$this->schema);
        (new SchemaTool($this->entityManager))->createSchema(array_map($this->entityManager->getClassMetadata(...), [
            Status::class, Account::class, Profile::class, ProfileExperience::class, AccountSetting::class,
            AdminRole::class, Admin::class, Organisation::class, ResetPasswordToken::class, VerifyEmailToken::class,
            Notification::class, Project::class, ProjectApplication::class, ProjectParticipant::class, ProjectRole::class,
            WorkPackage::class, PackageTask::class,
        ]));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->database !== null) {
                while ($this->database->isTransactionActive()) { $this->database->rollBack(); }
                if ($this->schemaCreated) { $this->database->executeStatement('DROP SCHEMA '.$this->schema.' CASCADE'); }
                $this->database->close();
            }
        } finally { parent::tearDown(); }
    }

    protected function browser(?Account $account = null, bool $mailFails = false): KernelBrowser
    {
        self::ensureKernelShutdown();
        $this->database->executeStatement('SET search_path TO '.$this->schema);
        $this->entityManager = new EntityManager($this->database, $this->configuration);
        $client = self::createClient();
        $client->disableReboot();
        $client->catchExceptions(true);
        $container = self::getContainer();
        $container->set('doctrine.orm.default_entity_manager', $this->entityManager);
        $container->get('cache.rate_limiter')->clear();
        $checker = $container->get(AccountUserChecker::class);
        $router = $container->get(UrlGeneratorInterface::class);
        $container->set(AccountUserProvider::class, new AccountUserProvider($this->entityManager, $checker));
        $container->set(AccountAuthenticator::class, new AccountAuthenticator($router, $this->entityManager));
        $this->logs = new TestHandler();
        foreach (['logger', 'monolog.logger.request', 'monolog.logger.security', 'monolog.logger.doctrine', 'monolog.logger.mailer'] as $id) {
            if ($container->has($id)) { $container->get($id)->pushHandler($this->logs); }
        }
        $nullMailer = new Mailer(new NullTransport($container->get('event_dispatcher')));
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $mail) use ($mailFails, $nullMailer): void {
            if ($mailFails) { throw new TransportException('Private transport failure: '.$mail->getTextBody()); }
            $nullMailer->send($mail);
            $this->messages[] = $mail;
        });
        $hasher = $container->get('security.user_password_hasher');
        $container->set(PasswordResetService::class, new PasswordResetService($this->entityManager, $mailer, $router, 'https://trusted.example.test', 'HNNW <no-reply@example.test>', $hasher));
        $container->set(RegistrationService::class, new RegistrationService($this->entityManager, $container->get(ValidatorInterface::class), $hasher, $mailer, $router, 'https://trusted.example.test', 'HNNW <no-reply@example.test>'));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn () => $this->entityManager);
        $projects = new ProjectRepository($registry);
        $container->set(ProjectRepository::class, $projects);
        $container->set(ProjectParticipantRepository::class, new ProjectParticipantRepository($registry));
        $container->set(WorkPackageRepository::class, new WorkPackageRepository($registry));
        $container->set(AccountDashboardService::class, new AccountDashboardService($this->entityManager, $projects));
        if ($account !== null) { $client->loginUser($account); }

        return $client;
    }

    protected function fixtureStatus(string $scope, string $name): Status
    {
        $status = $this->entityManager->getRepository(Status::class)->findOneBy(['scope' => $scope, 'name' => $name]);
        if ($status === null) {
            $status = (new Status())->setScope($scope)->setName($name)->setColourHex('#10B981');
            $this->entityManager->persist($status);
        }

        return $status;
    }

    protected function account(string $name, bool $verified = true, bool $withProfile = true, string $state = 'active'): Account
    {
        $account = (new Account())->setUsername($name)->setEmail($name.'@example.test')->setStatus($this->fixtureStatus('account', $state))
            ->setPasswordHash(password_hash('Test-password-123', PASSWORD_BCRYPT, ['cost' => 4]));
        if ($verified) { $account->verifyEmail(); }
        $this->entityManager->persist($account);
        if ($withProfile) {
            $profile = (new Profile())->setAccount($account)->setFirstName($name)->setLastName('Test')->setDisplayName($name.' display')->setAvatarUrl('');
            $account->setProfile($profile);
            $this->entityManager->persist($profile);
        }
        $this->entityManager->flush();

        return $account;
    }

    protected function project(Account $owner, string $title, string $visibility = 'private'): Project
    {
        $organisation = (new Organisation())->setName('Test organisation')->setSlug('org-'.bin2hex(random_bytes(5)))->setStatus($this->fixtureStatus('organisation', 'active'))->setOwnerAccount($owner);
        $project = (new Project())->setOwnerAccount($owner)->setOwnerOrganisation($organisation)->setStatus($this->fixtureStatus('project', 'active'))
            ->setTitle($title)->setSlug('project-'.bin2hex(random_bytes(5)))->setSummary('Test summary')->setDescription('Test description')
            ->setVisibility($visibility)->setCapacity(10)->setStartDate(new \DateTimeImmutable());
        if ($visibility === 'public') { $project->publish(); }
        $this->entityManager->persist($organisation);
        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    protected function login(KernelBrowser $client, string $email, string $password = 'Test-password-123', array $extra = []): void
    {
        $page = $client->request('GET', '/auth/login');
        $client->request('POST', '/auth/login', array_replace([
            'username' => $email, 'password' => $password,
            '_csrf_token' => $page->filter('input[name="_csrf_token"]')->attr('value'),
        ], $extra));
    }

    protected function resetToken(Account $account): array
    {
        $plain = bin2hex(random_bytes(32));
        $token = (new ResetPasswordToken())->setAccount($account)->setTokenHash(password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]));
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return [$plain, $token];
    }

    protected function snapshot(KernelBrowser $client, string $name): void
    {
        if ($directory = getenv('AUTH_SCREENSHOT_DIR')) {
            if (!is_dir($directory)) { mkdir($directory, 0777, true); }
            file_put_contents($directory.'/'.$name.'.html', $client->getResponse()->getContent());
        }
    }

    /** @return list<bool> */
    protected function concurrentRequests(string $mode, string $plainToken = ''): array
    {
        $processes = $inputs = [];
        try {
            if ($mode === 'reset') {
                $this->database->beginTransaction();
                $this->database->executeQuery('SELECT id FROM accounts FOR UPDATE')->fetchAllAssociative();
            }
            foreach ([1, 2] as $number) {
                $inputs[$number] = new InputStream();
                $processes[$number] = new Process([PHP_BINARY, __DIR__.'/auth-worker.php', $mode, $this->schema, (string) $number],
                    dirname(__DIR__, 4), ['AUTH_TEST_DATABASE_URL' => getenv('AUTH_TEST_DATABASE_URL'), 'AUTH_TEST_RESET_TOKEN' => $plainToken]);
                $processes[$number]->setTimeout(20);
                $processes[$number]->setInput($inputs[$number]);
                $processes[$number]->start();
            }
            foreach ($processes as $process) {
                self::assertTrue($process->waitUntil(static fn ($type, $output) => str_contains($output, 'READY')), $process->getErrorOutput());
            }
            foreach ($inputs as $input) { $input->write("go\n"); $input->close(); }
            if ($mode === 'reset') {
                $deadline = microtime(true) + 10;
                do {
                    // Pump stdin on Windows so both workers receive the start signal.
                    foreach ($processes as $process) { $process->isRunning(); }
                    $this->database->executeQuery('SELECT pg_stat_clear_snapshot()')->fetchOne();
                    $waiting = (int) $this->database->fetchOne("SELECT COUNT(*) FROM pg_stat_activity WHERE application_name IN (?, ?) AND wait_event_type = 'Lock'",
                        [$this->schema.'_1', $this->schema.'_2']);
                    if ($waiting === 2) { break; }
                    usleep(20000);
                } while (microtime(true) < $deadline);
                self::assertSame(2, $waiting, 'Both independent reset transactions must reach the account lock. '.implode(' ', array_map(static fn (Process $process) => $process->getErrorOutput(), $processes)));
                $this->database->commit();
            }
            $results = [];
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['success'];
            }

            return $results;
        } finally {
            if ($this->database->isTransactionActive()) { $this->database->rollBack(); }
            foreach ($inputs as $input) { $input->close(); }
            foreach ($processes as $process) { if ($process->isRunning()) { $process->stop(0); } }
        }
    }
}
