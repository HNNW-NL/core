<?php

namespace App\Tests\Module\Org;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Profile;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Module\Org\Service\StaffManagementService;
use App\Security\Permission;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Process\Process;

final class StaffManagementPostgresTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    private ?EntityManager $em = null;
    private string $schema;
    private bool $schemaCreated = false;
    private Organisation $organisation;
    private Account $actor;
    private Account $other;
    private OrgRole $viewer;
    private OrgMember $actorMembership;
    private OrgMember $actorRoleMembership;
    private OrgMember $otherMembership;

    protected function setUp(): void
    {
        if (!getenv('STAFF_TEST_DATABASE_URL')) {
            self::markTestSkipped('Set STAFF_TEST_DATABASE_URL to opt in to an isolated PostgreSQL schema.');
        }

        $this->schema = 'staff_test_'.bin2hex(random_bytes(8));
        $this->em = self::connect();
        $db = $this->em->getConnection();
        $db->executeStatement('CREATE SCHEMA '.$this->schema);
        $this->schemaCreated = true;
        $db->executeStatement('SET search_path TO '.$this->schema);

        $classes = [Status::class, Account::class, Profile::class, AccountSetting::class, AdminRole::class, Admin::class,
            Organisation::class, OrgRole::class, OrgMember::class];
        (new SchemaTool($this->em))->createSchema(array_map($this->em->getClassMetadata(...), $classes));

        $status = (new Status())->setName('Test')->setScope('account')->setColourHex('#000000');
        $this->organisation = (new Organisation())->setName('Staff Test')->setSlug('staff-test')->setStatus($status);
        $this->actor = (new Account())->setUsername('actor')->setEmail('actor@example.test')->setPasswordHash('test-only')->setStatus($status);
        $this->other = (new Account())->setUsername('other')->setEmail('other@example.test')->setPasswordHash('test-only')->setStatus($status);
        $this->viewer = $this->role('Viewer', Permission::ORG_VIEW);
        $manager = $this->role('Manager', Permission::ORG_VIEW, Permission::ORG_MANAGE_MEMBERS, Permission::ORG_MANAGE_ROLES);
        $membersManager = $this->role('Members manager', Permission::ORG_VIEW, Permission::ORG_MANAGE_MEMBERS);
        $rolesManager = $this->role('Roles manager', Permission::ORG_MANAGE_ROLES);
        $this->actorMembership = $this->member($this->actor, $membersManager);
        $this->actorRoleMembership = $this->member($this->actor, $rolesManager);
        $this->otherMembership = $this->member($this->other, $manager);
        $fixtures = [
            $status, $this->organisation, $this->actor, $this->other, $this->viewer,
            $manager, $membersManager, $rolesManager,
            $this->actorMembership, $this->actorRoleMembership, $this->otherMembership,
        ];
        foreach ($fixtures as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->em->clear();
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

    public function testDeferredDatabaseFailureRollsBackTheRoleUpdate(): void
    {
        $db = $this->em->getConnection();
        $db->executeStatement(<<<'SQL'
            CREATE FUNCTION reject_role_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'forced deferred database failure' USING ERRCODE = '23505';
            END;
            $$
            SQL);
        $db->executeStatement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER reject_role_change AFTER UPDATE ON org_members
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION reject_role_change()
            SQL);

        $memberId = (string) $this->otherMembership->getId();
        $originalRole = (string) $this->otherMembership->getOrgRole()->getId();
        try {
            $this->service()->changeRole($memberId, (string) $this->viewer->getId());
            self::fail('Expected the deferred PostgreSQL constraint to fail during commit.');
        } catch (\InvalidArgumentException $exception) {
            self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
            self::assertStringContainsString('forced deferred database failure', $exception->getPrevious()->getMessage());
            self::assertFalse($this->em->isOpen());
            self::assertFalse($db->isTransactionActive());
            self::assertSame($originalRole, $db->fetchOne('SELECT org_role_id FROM org_members WHERE id = ?', [$memberId]));
        }
    }

    public static function concurrentChanges(): iterable
    {
        yield 'remove then remove' => [false];
        yield 'remove then demote' => [true];
    }

    #[DataProvider('concurrentChanges')]
    public function testConcurrentChangesCannotRemoveTheLastEffectiveManager(bool $demote): void
    {
        $db = $this->em->getConnection();
        $actorMembershipId = (string) $this->actorMembership->getId();
        $otherMembershipId = (string) $this->otherMembership->getId();
        $db->beginTransaction();
        $command = [
            PHP_BINARY, '-r',
            'require $argv[1]; require $argv[2]; App\\Tests\\Module\\Org\\StaffManagementPostgresTest::runMutationWorker(...array_slice($argv, 3));',
            self::ROOT.'/vendor/autoload.php', __FILE__, $this->schema,
            (string) $this->actor->getId(), (string) $this->organisation->getId(), $actorMembershipId,
        ];
        if ($demote) {
            $command[] = (string) $this->viewer->getId();
        }
        $worker = new Process($command);
        $worker->setTimeout(20);

        try {
            // The outer transaction keeps the first service call's write and organisation lock open.
            $this->service()->removeMember($otherMembershipId);
            $worker->start();
            $deadline = microtime(true) + 8;
            do {
                // Statistics are otherwise cached for the duration of our open transaction.
                $db->executeQuery('SELECT pg_stat_clear_snapshot()');
                $waiting = (int) $db->fetchOne(
                    "SELECT COUNT(*) FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'",
                    [$this->schema],
                );
                if ($waiting === 1 || !$worker->isRunning()) {
                    break;
                }
                usleep(20_000);
            } while (microtime(true) < $deadline);
            self::assertSame(1, $waiting, 'The second request must wait for the first organisation lock. '.$worker->getErrorOutput());

            $db->commit();
            self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
            self::assertSame('last manager protected', trim($worker->getOutput()));
            self::assertEqualsCanonicalizing(
                [$actorMembershipId, (string) $this->actorRoleMembership->getId()],
                $db->fetchFirstColumn('SELECT id FROM org_members'),
            );
            self::assertSame(
                (string) $this->actorMembership->getOrgRole()->getId(),
                $db->fetchOne('SELECT org_role_id FROM org_members WHERE id = ?', [$actorMembershipId]),
            );
        } finally {
            $worker->stop();
        }
    }

    public static function runMutationWorker(string $schema, string $actorId, string $organisationId, string $memberId, ?string $roleId = null): void
    {
        if (!preg_match('/^staff_test_[0-9a-f]{16}$/D', $schema)) {
            throw new \InvalidArgumentException('Invalid test schema.');
        }
        $em = self::connect();
        $db = $em->getConnection();
        $db->executeStatement('SET search_path TO '.$schema);
        $db->executeQuery("SELECT set_config('application_name', ?, false)", [$schema]);
        $db->executeStatement("SET lock_timeout TO '12s'");
        try {
            $service = new StaffManagementService($em, self::session($actorId, $organisationId));
            if ($roleId === null) {
                $service->removeMember($memberId);
            } else {
                $service->changeRole($memberId, $roleId);
            }
            throw new \LogicException('Concurrent request removed the last effective manager.');
        } catch (\InvalidArgumentException $exception) {
            if (!str_contains($exception->getMessage(), 'De organisatie moet minstens één bevoegd account')) {
                throw $exception;
            }
            echo 'last manager protected';
        } finally {
            $db->close();
        }
    }

    private static function connect(): EntityManager
    {
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse(getenv('STAFF_TEST_DATABASE_URL'));
        if (($params['driver'] ?? null) !== 'pdo_pgsql'
            || !in_array($params['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true)
        ) {
            throw new \InvalidArgumentException('Use a local PostgreSQL instance for Staff Management integration tests.');
        }
        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }
        $config = ORMSetup::createAttributeMetadataConfig([self::ROOT.'/src/Entity']);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));

        return new EntityManager(DriverManager::getConnection($params), $config);
    }

    private static function session(string $actorId, string $organisationId): RequestStack
    {
        $request = Request::create('/org/staff?organisation_id='.$organisationId, 'POST', cookies: ['PHPSESSID' => 'test']);
        $request->setSession(new Session(new MockArraySessionStorage('PHPSESSID')));
        $request->getSession()->set('account_id', $actorId);
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    private function service(): StaffManagementService
    {
        return new StaffManagementService(
            $this->em, self::session((string) $this->actor->getId(), (string) $this->organisation->getId()),
        );
    }

    private function role(string $name, Permission ...$permissions): OrgRole
    {
        return (new OrgRole())->setOrganisation($this->organisation)->setName($name)->setPermissionsMask(Permission::maskFor($permissions));
    }

    private function member(Account $account, OrgRole $role): OrgMember
    {
        return (new OrgMember())->setOrganisation($this->organisation)->setAccount($account)->setOrgRole($role);
    }
}
