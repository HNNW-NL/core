<?php

namespace App\Tests\Module\Org;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Module\Org\Controller\StaffManagementController;
use App\Module\Org\Service\StaffManagementService;
use App\Module\Org\Service\StaffViewService;
use App\Security\Permission;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as PdoDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class StaffManagementServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private StaffManagementService $service;
    private Organisation $org;
    private OrgMember $actor;
    private Account $targetAccount;
    private OrgMember $target;
    private OrgRole $viewer;
    private OrgRole $foreignRole;
    private OrgRole $privilegedRole;
    private array $members;
    private RequestStack $stack;

    protected function setUp(): void
    {
        $this->org = new Organisation();
        $this->viewer = $this->role($this->org, Permission::ORG_VIEW);
        $this->foreignRole = $this->role(new Organisation(), Permission::ORG_VIEW);
        $this->privilegedRole = $this->role($this->org, Permission::ORG_VIEW, Permission::ORG_DELETE);
        $this->actor = $this->member(new Account(), $this->role(
            $this->org, Permission::ORG_VIEW, Permission::ORG_MANAGE_MEMBERS, Permission::ORG_MANAGE_ROLES,
        ));
        $this->targetAccount = (new Account())->setEmail('staff-test@example.test');
        $this->target = $this->member($this->targetAccount, $this->viewer);
        $this->members = [$this->actor, $this->target];

        $request = Request::create('/org/staff?organisation_id='.$this->org->getId(), 'POST', cookies: ['PHPSESSID' => 'test']);
        $request->setSession(new Session(new MockArraySessionStorage('PHPSESSID')));
        $request->getSession()->set('account_id', (string) $this->actor->getAccount()->getId());
        $this->stack = new RequestStack();
        $this->stack->push($request);

        $accounts = $this->createStub(EntityRepository::class);
        $accounts->method('findOneBy')->willReturn($this->targetAccount);
        $members = $this->createStub(EntityRepository::class);
        $members->method('findBy')->willReturnCallback(
            fn (array $criteria): array => isset($criteria['account'])
                ? array_values(array_filter($this->members, static fn (OrgMember $member): bool => $member->getAccount() === $criteria['account']))
                : $this->members,
        );

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('getRepository')->willReturnMap([[Account::class, $accounts], [OrgMember::class, $members]]);
        $this->em->method('wrapInTransaction')->willReturnCallback(fn (callable $operation): mixed => $operation($this->em));
        $this->em->method('find')->willReturnCallback(function (string $class, mixed $id, mixed $lock = null): ?object {
            if ($class === Organisation::class) {
                self::assertSame(LockMode::PESSIMISTIC_WRITE, $lock);
            }
            foreach ([$this->org, $this->actor->getAccount(), $this->actor, $this->target, $this->viewer, $this->foreignRole, $this->privilegedRole] as $entity) {
                if ($entity instanceof $class && (string) $entity->getId() === (string) $id) {
                    return $entity;
                }
            }

            return null;
        });
        $this->service = new StaffManagementService($this->em, $this->stack);
    }

    public function testAddsAccountWithOrganisationRole(): void
    {
        $this->em->expects(self::once())->method('persist');
        $member = $this->service->addMember('staff-test@example.test', (string) $this->viewer->getId());

        self::assertSame($this->targetAccount, $member->getAccount());
        self::assertSame($this->org, $member->getOrganisation());
        self::assertSame($this->viewer, $member->getOrgRole());
    }

    public function testRejectsRoleFromAnotherOrganisation(): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->expectExceptionMessage('De gekozen rol hoort niet bij jouw organisatie.');
        $this->service->addMember('staff-test@example.test', (string) $this->foreignRole->getId());
    }

    public function testCannotAddMemberWithPermissionTheActorDoesNotHave(): void
    {
        $this->em->expects(self::never())->method('persist');
        $this->expectExceptionMessage('Je kunt geen rol toewijzen met rechten die je zelf niet hebt.');
        $this->service->addMember('staff-test@example.test', (string) $this->privilegedRole->getId());
    }

    public function testCannotPromoteMemberWithPermissionTheActorDoesNotHave(): void
    {
        $this->em->expects(self::never())->method('persist');
        try {
            $this->service->changeRole((string) $this->target->getId(), (string) $this->privilegedRole->getId());
            self::fail('Expected the role assignment to be rejected.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('Je kunt geen rol toewijzen met rechten die je zelf niet hebt.', $e->getMessage());
            self::assertSame($this->viewer, $this->target->getOrgRole());
        }
    }

    #[DataProvider('mutations')]
    public function testProtectsManagementPermissionSplitAcrossMemberships(bool $changeRole): void
    {
        $membersRole = $this->actor->getOrgRole();
        $membersRole->setPermissionsMask(Permission::maskFor([Permission::ORG_VIEW, Permission::ORG_MANAGE_MEMBERS]));
        $rolesRole = $this->role($this->org, Permission::ORG_MANAGE_ROLES);
        $this->members[] = $this->member($this->actor->getAccount(), $rolesRole);
        $this->em->expects(self::never())->method('remove');

        try {
            $this->mutate($changeRole, (string) $this->actor->getId());
            self::fail('Expected the last combined manager to be protected.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('De organisatie moet minstens één bevoegd account', $e->getMessage());
            self::assertSame($membersRole, $this->actor->getOrgRole());
        }
    }

    public function testChangesRoleWhenAnotherMembershipKeepsManagementPermissions(): void
    {
        $this->em->expects(self::never())->method('remove');
        $otherRole = $this->role($this->org, Permission::ORG_MANAGE_MEMBERS, Permission::ORG_MANAGE_ROLES);
        $this->members[] = $this->member($this->actor->getAccount(), $otherRole);
        $updated = $this->service->changeRole((string) $this->actor->getId(), (string) $this->viewer->getId());

        self::assertSame($this->viewer, $updated->getOrgRole());
    }

    public function testRemovesRegularMember(): void
    {
        $this->em->expects(self::once())->method('remove')->with($this->target);
        $this->service->removeMember((string) $this->target->getId());
    }

    public function testRejectsMutationWithoutExplicitOrganisation(): void
    {
        $this->stack->getCurrentRequest()->query->remove('organisation_id');
        $this->em->expects(self::never())->method('wrapInTransaction');
        $this->expectExceptionMessage('Kies eerst een geldige organisatie.');
        $this->service->removeMember((string) $this->target->getId());
    }

    public function testViewSelectsAnOrganisationAndKeepsPermissionsScoped(): void
    {
        $this->em->expects(self::never())->method('wrapInTransaction');
        $account = $this->actor->getAccount();
        $otherOrg = $this->foreignRole->getOrganisation();
        $otherMembership = $this->member($account, $this->foreignRole)->setOrganisation($otherOrg);
        $account->getOrgMemberships()->add($this->actor);
        $account->getOrgMemberships()->add($otherMembership);

        $accounts = $this->createStub(EntityRepository::class);
        $accounts->method('find')->willReturn($account);
        $members = $this->createMock(EntityRepository::class);
        $members->expects(self::once())->method('findBy')->with(['organisation' => $otherOrg])->willReturn([$otherMembership]);
        $roles = $this->createStub(EntityRepository::class);
        $roles->method('findBy')->willReturn([$this->foreignRole]);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getRepository')->willReturnMap([
            [Account::class, null, $accounts], [OrgMember::class, null, $members], [OrgRole::class, null, $roles],
        ]);
        $view = new StaffViewService($registry, $this->stack);
        $query = $this->stack->getCurrentRequest()->query;
        $query->remove('organisation_id');
        self::assertNull($view->getStaff()['organisationId']);

        $query->set('organisation_id', (string) $otherOrg->getId());
        $data = $view->getStaff();
        self::assertSame((string) $otherOrg->getId(), $data['organisationId']);
        self::assertSame((string) $otherMembership->getId(), $data['items'][0]['id']);
        self::assertFalse($data['canAdd']);
        self::assertFalse($data['canChangeRole']);
        self::assertFalse($data['canRemove']);
    }

    public function testDatabaseFailureRollsBackAndClosesEntityManager(): void
    {
        $this->em->expects(self::never())->method('wrapInTransaction');
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::never())->method('commit');
        $connection->method('isTransactionActive')->willReturn(true);
        $connection->expects(self::once())->method('rollBack');

        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([]));
        $config->enableNativeLazyObjects(true);
        $em = $this->getMockBuilder(EntityManager::class)
            ->setConstructorArgs([$connection, $config])
            ->onlyMethods(['find', 'getRepository', 'persist', 'flush'])
            ->getMock();
        $em->method('find')->willReturnCallback($this->em->find(...));
        $em->method('getRepository')->willReturnCallback($this->em->getRepository(...));
        $em->expects(self::once())->method('persist');
        $failure = new UniqueConstraintViolationException(PdoDriverException::new(new \PDOException('Duplicate key')), null);
        $em->expects(self::once())->method('flush')->willThrowException($failure);

        try {
            (new StaffManagementService($em, $this->stack))->addMember('staff-test@example.test', (string) $this->viewer->getId());
            self::fail('Expected a validation error for the unique constraint violation.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame($failure, $e->getPrevious());
            self::assertFalse($em->isOpen());
        }
    }

    public static function mutations(): iterable
    {
        yield 'change role' => [true];
        yield 'remove' => [false];
    }

    #[DataProvider('mutations')]
    public function testRoleNameAloneDoesNotGrantAccess(bool $changeRole): void
    {
        $this->actor->getOrgRole()->setName('Beheerder')->setPermissionsMask(Permission::ORG_VIEW->value);
        $this->em->expects(self::never())->method('remove');
        $this->expectExceptionMessage('Je hebt onvoldoende rechten voor deze actie.');
        $this->mutate($changeRole, (string) $this->target->getId());
    }

    #[DataProvider('mutations')]
    public function testProtectsLastManagerRegardlessOfRoleName(bool $changeRole): void
    {
        $this->em->expects(self::never())->method('remove');
        $this->expectExceptionMessage('De organisatie moet minstens één bevoegd account');
        $this->mutate($changeRole, (string) $this->actor->getId());
    }

    #[DataProvider('mutations')]
    public function testRejectsInvalidMemberId(bool $changeRole): void
    {
        $this->em->expects(self::never())->method('remove');
        $this->expectExceptionMessage('Ongeldige medewerker-ID.');
        $this->mutate($changeRole, 'invalid');
    }

    public static function csrfActions(): iterable
    {
        yield ['add', 'staff_add'];
        yield ['changeRole', 'staff_role_member'];
        yield ['delete', 'staff_delete_member'];
    }

    #[DataProvider('csrfActions')]
    public function testControllerRejectsInvalidCsrfBeforeStartingTransaction(string $action, string $tokenId): void
    {
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects(self::once())->method('isTokenValid')->with(self::callback(
            static fn ($token): bool => $token->getId() === $tokenId && $token->getValue() === 'invalid',
        ))->willReturn(false);
        $container = new Container();
        $container->set('security.csrf.token_manager', $csrf);
        $controller = new StaffManagementController();
        $controller->setContainer($container);
        $request = Request::create('/org/staff', 'POST', ['_token' => 'invalid']);

        $this->em->expects(self::never())->method('wrapInTransaction');
        $this->expectException(AccessDeniedException::class);
        $controller->$action(...($action === 'add' ? [$request, $this->service] : ['member', $request, $this->service]));
    }

    private function mutate(bool $changeRole, string $memberId): void
    {
        if ($changeRole) {
            $this->service->changeRole($memberId, (string) $this->viewer->getId());
        } else {
            $this->service->removeMember($memberId);
        }
    }

    private function member(Account $account, OrgRole $role): OrgMember
    {
        return (new OrgMember())->setAccount($account)->setOrganisation($this->org)->setOrgRole($role);
    }

    private function role(Organisation $org, Permission ...$permissions): OrgRole
    {
        return (new OrgRole())->setName('Testrol')->setOrganisation($org)->setPermissionsMask(Permission::maskFor($permissions));
    }
}
