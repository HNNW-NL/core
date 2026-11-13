<?php

namespace App\Tests\Module\AccountCentre;

use App\Entity\Account\{Account, AccountSetting, Notification, Profile};
use App\Entity\Admin\{Admin, AdminRole};
use App\Entity\Auth\{ResetPasswordToken, VerifyEmailToken};
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\{Project, ProjectApplication, ProjectParticipant, ProjectRole};
use App\Kernel;
use App\Module\AccountCentre\Service\AccountDashboardService;
use App\Module\Auth\Service\RegistrationService;
use App\Repository\Account\AccountRepository;
use App\Repository\Account\ProfileRepository;
use App\Security\{AccountAuthenticator, AccountUserProvider};
use Doctrine\DBAL\DriverManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ContainerRepositoryFactory;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\{Mailer, MailerInterface};
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\PasswordHasher\Hasher\{NativePasswordHasher, PasswordHasherFactory, UserPasswordHasher};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class AccountOnboardingPostgresTest extends WebTestCase
{
    private ?EntityManager $em = null;
    private bool $schemaCreated = false;
    private string $schema;
    private array $statuses = [];
    private int $sequence = 0;

    protected static function getKernelClass(): string { return Kernel::class; }

    protected function setUp(): void
    {
        parent::setUp();
        if (!getenv('AUTH_TEST_DATABASE_URL')) {
            self::markTestSkipped('Set AUTH_TEST_DATABASE_URL to use an isolated local PostgreSQL schema.');
        }
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse(getenv('AUTH_TEST_DATABASE_URL'));
        if (($params['driver'] ?? null) !== 'pdo_pgsql' || !in_array($params['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \InvalidArgumentException('Authentication tests require local PostgreSQL.');
        }
        // Existing Table::uniqueConstraints metadata is a separate migration.
        \Doctrine\Deprecations\Deprecation::ignoreDeprecations('https://github.com/doctrine/orm/pull/11357');
        if (!Type::hasType(UuidType::NAME)) { Type::addType(UuidType::NAME, UuidType::class); }
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/../../../src/Entity']);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $repositoryRegistry = $this->createStub(ManagerRegistry::class);
        $repositoryRegistry->method('getManagerForClass')->willReturnCallback(fn () => $this->em);
        $config->setRepositoryFactory(new ContainerRepositoryFactory(new ServiceLocator([
            ProfileRepository::class => static fn () => new ProfileRepository($repositoryRegistry),
        ])));
        $this->em = new EntityManager(DriverManager::getConnection($params), $config);
        $this->schema = 'auth_onboarding_test_'.bin2hex(random_bytes(8));
        $db = $this->em->getConnection();
        $db->executeStatement('CREATE SCHEMA '.$this->schema);
        $this->schemaCreated = true;
        $db->executeStatement('SET search_path TO '.$this->schema);
        (new SchemaTool($this->em))->createSchema(array_map($this->em->getClassMetadata(...), [
            Status::class, Account::class, Profile::class, AccountSetting::class, AdminRole::class, Admin::class,
            ResetPasswordToken::class, VerifyEmailToken::class, Notification::class, Organisation::class,
            Project::class, ProjectRole::class, ProjectApplication::class, ProjectParticipant::class,
        ]));
        $this->fixtureStatus('account', 'active');
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if ($this->em !== null) {
            $db = $this->em->getConnection();
            try {
                while ($db->isTransactionActive()) { $db->rollBack(); }
                if ($this->schemaCreated) { $db->executeStatement('DROP SCHEMA '.$this->schema.' CASCADE'); }
            } finally { $db->close(); }
        }
        parent::tearDown();
    }

    public function testDashboardTotalsMatchLinkedRecordsAndExcludeOtherAccounts(): void
    {
        $actor = $this->account('actor');
        $actor->verifyEmail();
        $other = $this->account('other');
        $this->application($actor, 'send', 'Own pending');
        $this->application($actor, 'accepted', 'Own accepted');
        $removed = $this->application($actor, 'pending', 'Removed application'); $removed->softDelete();
        $this->participation($actor, 'Own project one');
        $this->participation($actor, 'Own project two');
        $left = $this->participation($actor, 'Left project'); $left->leave();
        $deleted = $this->participation($actor, 'Removed project'); $deleted->getProject()->softDelete();
        $this->application($other, 'send', 'Other private application');
        $this->participation($other, 'Other private project');
        $this->notice($actor, 'Own unread');
        $read = $this->notice($actor, 'Read notice'); $read->markAsRead();
        $archived = $this->notice($actor, 'Removed notice'); $archived->softDelete();
        $this->notice($other, 'Other private notice');
        $this->em->flush();
        $client = $this->browser($actor);
        $client->request('GET', '/account?account_id='.$other->getId().'&profile_id='.$other->getProfile()->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#account-total-applications', '1');
        self::assertSelectorTextSame('#account-total-projects', '2');
        self::assertSelectorTextSame('#account-total-notifications', '1');
        self::assertSelectorTextSame('#account-name', 'actor display');
        self::assertSelectorTextSame('#email-status', 'Bevestigd');
        self::assertSelectorExists('a[href="/profile"]');
        self::assertSelectorExists('a[href="/account/settings"]');
        $this->snapshot($client, 'dashboard-populated');
        $client->request('GET', '/account/applications?status=pending&account_id='.$other->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.account-records article');
        self::assertSelectorTextContains('.account-records', 'Own pending');
        self::assertStringNotContainsString('Other private', $client->getResponse()->getContent());
        $client->request('GET', '/account/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.account-records article');
        self::assertStringNotContainsString('Other private', $client->getResponse()->getContent());
        $client->request('GET', '/account/messages');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.account-records article');
        self::assertSelectorTextContains('.account-records', 'Own unread');
    }

    public function testNewAccountHasUsefulEmptyDashboardAndProfileLinks(): void
    {
        $actor = $this->account('new');
        $actor->getProfile()->setDisplayName('<script>alert(1)</script>');
        $this->em->flush();
        $client = $this->browser($actor);
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        foreach (['applications', 'projects', 'notifications'] as $name) { self::assertSelectorTextSame('#account-total-'.$name, '0'); }
        self::assertSelectorTextSame('#email-status', 'Nog niet bevestigd');
        self::assertSelectorExists('#account-empty a[href="/account/modify"]');
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        $this->snapshot($client, 'dashboard-empty');
        $client->request('GET', '/account/modify');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="username"]');
    }

    public function testProfileShortcutOpensWithUuidAccountStatusAndShowsProfilePresence(): void
    {
        $actor = $this->account('profile-shortcut');
        $client = $this->browser($actor);
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        $client->click($client->getCrawler()->filter('.account-shortcuts a[href="/profile"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#profile-presence', 'Status: Offline');

        $this->em->find(Profile::class, $actor->getProfile()->getId())->goOnline();
        $this->em->flush();
        $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#profile-presence', 'Status: Online');
    }

    public function testMembershipWithoutViewPermissionHasAnExplanationInsteadOfAForbiddenLink(): void
    {
        $owner = $this->account('project-owner');
        $member = $this->account('project-member');
        $project = $this->project($owner, 'Restricted project');
        $participant = (new ProjectParticipant())->setProject($project)->setProfile($member->getProfile())->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
        $participant->join(); $this->em->persist($participant); $this->em->flush();
        $client = $this->browser($member);
        $client->request('GET', '/account/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.account-records article');
        self::assertSelectorNotExists('.account-records article a');
        self::assertSelectorTextContains('.account-records', 'Je projectrol geeft momenteel geen toegang');
    }

    public function testAccountWithoutProfileAlsoHasZeroTotals(): void
    {
        $actor = $this->account('no-profile', false);
        $client = $this->browser($actor);
        $client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#account-name', 'no-profile');
        self::assertSelectorExists('#account-empty');
        $client->click($client->getCrawler()->filter('.account-shortcuts a[href="/profile"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('#profile-presence', 'Status: Offline');
    }

    public function testTwentyOneRecordsArePaginatedWithoutLosingCount(): void
    {
        $actor = $this->account('pages');
        for ($i = 0; $i < 21; ++$i) { $this->notice($actor, 'Message '.$i); }
        $this->em->flush();
        $client = $this->browser($actor);
        $client->request('GET', '/account');
        self::assertSelectorTextSame('#account-total-notifications', '21');
        $client->request('GET', '/account/messages');
        self::assertSelectorCount(20, '.account-records article');
        self::assertSelectorExists('a[rel="next"][href="/account/messages?page=2"]');
        $client->request('GET', '/account/messages?page=2');
        self::assertSelectorCount(1, '.account-records article');
        self::assertSelectorTextContains('.account-pagination', 'Pagina 2 van 2');
    }

    public function testRegistrationNormalisesEmailAndVerificationCompletesOnboarding(): void
    {
        $client = $this->browser();
        $this->register($client, ['email' => '  New.Person@Example.Test  ', 'roles' => ['ROLE_ADMIN'], 'email_verified_at' => '2020-01-01']);
        self::assertResponseRedirects('/auth/login?last=new.person@example.test', 303);
        self::assertEmailCount(1);
        $account = $this->em->getRepository(Account::class)->findOneBy(['email' => 'new.person@example.test']);
        self::assertNotNull($account);
        self::assertNull($account->getEmailVerifiedAt());
        self::assertSame(['ROLE_USER'], $account->getRoles());
        self::assertTrue(self::getContainer()->get('security.user_password_hasher')->isPasswordValid($account, 'Test-password-123'));
        self::assertSame(1, $this->em->getRepository(Profile::class)->count([]));
        self::assertSame(1, $this->em->getRepository(VerifyEmailToken::class)->count([]));
        $mail = self::getMailerMessage();
        self::assertEmailHtmlBodyContains($mail, 'E-mailadres bevestigen');
        self::assertEmailTextBodyNotContains($mail, 'Test-password-123');
        preg_match('~http://localhost/auth/verify-email/[^\s]+~', $mail->getTextBody(), $matches);
        self::assertNotEmpty($matches);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Controleer je e-mail');
        $client->request('GET', $matches[0]);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'E-mailadres bevestigd.');
        $account = $this->em->find(Account::class, $account->getId());
        self::assertNotNull($account->getEmailVerifiedAt());
        $client->loginUser($account);
        $client->request('GET', '/account');
        self::assertSelectorTextSame('#account-name', 'Test Persoon');
        self::assertSelectorTextSame('#email-status', 'Bevestigd');
        self::assertSelectorExists('#account-empty');
    }

    public function testServerValidationPreservesSafeFieldsAndNeverEchoesPasswords(): void
    {
        $client = $this->browser();
        $this->register($client, ['full_name' => '<script>alert(1)</script>', 'email' => 'invalid', 'password' => 'short', 'confirm_password' => 'different']);
        self::assertResponseStatusCodeSame(422);
        foreach (['email', 'password', 'confirm_password'] as $field) { self::assertSelectorExists('#'.$field.'-error:not(.visually-hidden)'); }
        self::assertSelectorExists('input[name="full_name"][value="<script>alert(1)</script>"]');
        self::assertSelectorNotExists('input[type="password"][value]');
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        self::assertStringNotContainsString('value="short"', $client->getResponse()->getContent());
        self::assertSame(0, $this->em->getRepository(Account::class)->count([]));
        self::assertEmailCount(0);
        $this->snapshot($client, 'registration-errors');
    }

    public function testMissingCsrfCannotCreateAnAccount(): void
    {
        $client = $this->browser();
        $client->request('POST', '/auth/register', $this->registrationData());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#register-errors', 'Het formulier is verlopen.');
        self::assertSame(0, $this->em->getRepository(Account::class)->count([]));
    }

    public function testEmailCaseAndRepeatedRequestNeverCreateSecondAccount(): void
    {
        $client = $this->browser();
        $this->register($client);
        self::assertResponseStatusCodeSame(303);
        $this->register($client, ['username' => 'different-name', 'email' => ' NEW.PERSON@EXAMPLE.TEST ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#email-error', 'al in gebruik');
        self::assertSame(1, $this->em->getRepository(Account::class)->count([]));
        self::assertSame(1, $this->em->getRepository(VerifyEmailToken::class)->count([]));
    }

    public function testLegacyMixedCaseEmailAlsoPreventsDuplicateRegistration(): void
    {
        $account = $this->account('legacy');
        $account->setEmail('New.Person@Example.Test');
        $this->em->flush();
        $client = $this->browser();
        $this->register($client);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#email-error', 'al in gebruik');
        self::assertSame(1, $this->em->getRepository(Account::class)->count([]));
    }

    public function testMailFailureRollsBackAccountProfileAndToken(): void
    {
        $client = $this->browser(mailFails: true);
        $this->register($client);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#register-errors', 'Registreren is tijdelijk niet mogelijk.');
        foreach (['accounts', 'profiles', 'verify_email_tokens'] as $table) {
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
    }

    public function testDatabaseUniquenessConflictIsHandledWithoutPartialRegistration(): void
    {
        $existing = $this->account('existing');
        $existing->setEmail('new.person@example.test');
        $this->em->flush();
        $client = $this->browser(forceMissingAccount: true);
        $this->register($client);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#register-errors', 'al in gebruik');
        $db = $this->em->getConnection();
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM accounts'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM profiles'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verify_email_tokens'));
        self::assertEmailCount(0);
    }

    public function testVerificationTokenCannotConfirmAnotherEmailOrBeReused(): void
    {
        $owner = $this->account('owner');
        $other = $this->account('other');
        $plain = bin2hex(random_bytes(32));
        $token = (new VerifyEmailToken())->setAccount($owner)->setEmailToVerify($owner->getEmail())->setTokenHash(password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]));
        $this->em->persist($token); $this->em->flush();
        $client = $this->browser();
        $client->request('GET', '/auth/verify-email/'.$plain.'/'.$other->getEmail());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Ongeldige of verlopen');
        self::assertNull($this->em->find(Account::class, $other->getId())->getEmailVerifiedAt());
        $client->request('GET', '/auth/verify-email/'.$plain.'/'.$owner->getEmail());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'E-mailadres bevestigd.');
        $client->request('GET', '/auth/verify-email/'.$plain.'/'.$owner->getEmail());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Ongeldige of verlopen');
    }

    private function browser(?Account $account = null, bool $mailFails = false, bool $forceMissingAccount = false): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->catchExceptions(false);
        $container = self::getContainer();
        $container->set('doctrine.orm.default_entity_manager', $this->em);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->em);
        $accounts = $forceMissingAccount ? $this->createStub(AccountRepository::class) : new AccountRepository($registry);
        $mailer = new Mailer(new NullTransport($container->get('event_dispatcher')));
        if ($mailFails) {
            $mailer = $this->createStub(MailerInterface::class);
            $mailer->method('send')->willThrowException(new TransportException('Simulated mail failure'));
        }
        $router = $container->get(UrlGeneratorInterface::class);
        $container->set(AccountUserProvider::class, new AccountUserProvider($this->em));
        $container->set(AccountAuthenticator::class, new AccountAuthenticator($router, $this->em));
        $container->set(AccountDashboardService::class, new AccountDashboardService($this->em));
        $container->set(RegistrationService::class, new RegistrationService($this->em, $accounts,
            $container->get(ValidatorInterface::class), $container->get('security.user_password_hasher'), $mailer,
            $router, 'http://localhost', 'HNNW <no-reply@example.test>'));
        if ($account !== null) { $client->loginUser($account); }
        return $client;
    }

    private function registrationData(): array
    {
        return ['full_name' => 'Test Persoon', 'username' => 'new-person', 'email' => 'new.person@example.test',
            'password' => 'Test-password-123', 'confirm_password' => 'Test-password-123', 'terms' => '1'];
    }

    private function register(KernelBrowser $client, array $overrides = []): void
    {
        $crawler = $client->request('GET', '/auth/register');
        $data = array_replace($this->registrationData(), $overrides);
        $data['_csrf_token'] = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/auth/register', $data);
    }

    private function fixtureStatus(string $scope, string $name): Status
    {
        $key = $scope.':'.$name;
        if (!isset($this->statuses[$key])) {
            $this->statuses[$key] = (new Status())->setScope($scope)->setName($name)->setColourHex('#10B981');
            $this->em->persist($this->statuses[$key]);
        }
        return $this->statuses[$key];
    }

    private function account(string $name, bool $profile = true): Account
    {
        $account = (new Account())->setUsername($name)->setEmail($name.'@example.test')->setStatus($this->fixtureStatus('account', 'active'));
        $account->setPasswordHash((new UserPasswordHasher(new PasswordHasherFactory([Account::class => new NativePasswordHasher(cost: 4)])))->hashPassword($account, 'Test-password-123'));
        $this->em->persist($account);
        if ($profile) {
            $person = (new Profile())->setAccount($account)->setFirstName($name)->setLastName('Test')->setDisplayName($name.' display')->setAvatarUrl('');
            $account->setProfile($person); $this->em->persist($person);
        }
        $this->em->flush();
        return $account;
    }

    private function project(Account $account, string $title): Project
    {
        $org = (new Organisation())->setName('Test Organisation')->setSlug('org-'.++$this->sequence)->setStatus($this->fixtureStatus('organisation', 'active'));
        $project = (new Project())->setOwnerAccount($account)->setOwnerOrganisation($org)->setStatus($this->fixtureStatus('project', 'active'))
            ->setTitle($title)->setSlug('project-'.$this->sequence)->setSummary('Test project summary')->setDescription('Test project description')
            ->setStartDate(new \DateTimeImmutable())->setCapacity(10)->setVisibility('public');
        $this->em->persist($org); $this->em->persist($project);
        return $project;
    }

    private function application(Account $account, string $status, string $title): ProjectApplication
    {
        $application = (new ProjectApplication())->setProfile($account->getProfile())->setProject($this->project($account, $title))->setStatus($this->fixtureStatus('ProjectApplication', $status));
        $this->em->persist($application);
        return $application;
    }

    private function participation(Account $account, string $title): ProjectParticipant
    {
        $participant = (new ProjectParticipant())->setProfile($account->getProfile())->setProject($this->project($account, $title))->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
        $participant->join(); $this->em->persist($participant);
        return $participant;
    }

    private function notice(Account $account, string $title): Notification
    {
        $notification = (new Notification())->setAccount($account)->setType('test')->setTitle($title)->setMessage('Test notification message');
        $this->em->persist($notification);
        return $notification;
    }

    private function snapshot(KernelBrowser $client, string $name): void
    {
        if ($directory = getenv('AUTH_SCREENSHOT_DIR')) {
            if (!is_dir($directory)) { mkdir($directory, 0777, true); }
            file_put_contents($directory.'/'.$name.'.html', $client->getResponse()->getContent());
        }
    }
}
