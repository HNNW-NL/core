<?php

namespace App\Tests\Module\Admin\Controller;

use App\Entity\Account\Account;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Entity\Project\Project;
use App\Kernel;
use App\Module\Admin\Service\DashboardTotals;
use App\Module\Admin\Service\AccountOverview;
use App\Security\AccountUserProvider;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AdminHomeTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects('/auth/login');
    }

    public static function deniedRoles(): iterable
    {
        yield 'ordinary account' => ['account'];
        yield 'organisation administrator' => ['organisation'];
        yield 'platform administrator without dashboard permission' => ['platform'];
    }

    #[DataProvider('deniedRoles')]
    public function testAccountWithoutDashboardPermissionIsDenied(string $role): void
    {
        $account = (new Account())->setUsername('ad01-test');
        if ($role === 'organisation') {
            $organisation = new Organisation();
            $orgRole = (new OrgRole())->setOrganisation($organisation)
                ->setPermissionsMask(Permission::ORG_MANAGE_MEMBERS->value);
            $account->getOrgMemberships()->add((new OrgMember())->setAccount($account)
                ->setOrganisation($organisation)->setOrgRole($orgRole));
        }
        if ($role === 'platform') {
            $account->setAdmin((new Admin())->setAccount($account)->setAdminRole(new AdminRole()));
        }

        $client = $this->clientFor($account);
        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public static function totals(): iterable
    {
        yield 'populated' => [21, 3, 8];
        yield 'empty' => [0, 0, 0];
    }

    #[DataProvider('totals')]
    public function testPlatformAdministratorSeesDashboardTotalsAndNavigation(int $accounts, int $organisations, int $projects): void
    {
        $client = $this->clientFor($this->platformAdministrator(), [
            Account::class => $accounts, Organisation::class => $organisations, Project::class => $projects,
        ]);
        $client->catchExceptions(false);
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        foreach (['accounts' => $accounts, 'organisations' => $organisations, 'projects' => $projects] as $key => $count) {
            self::assertSelectorTextSame('#dashboard-total-'.$key, (string) $count);
        }
        foreach (['accounts', 'organizations', 'logs', 'statuses', 'notifications', 'settings', 'social/manage-staff', 'events'] as $path) {
            self::assertSelectorExists(sprintf('nav a[href="/admin/%s"]', $path));
        }
        if ($accounts === 0 && $organisations === 0 && $projects === 0) {
            self::assertSelectorTextContains('#dashboard-empty', 'Er zijn nog geen');
        }
    }

    public function testDashboardOnlyAdministratorSeesLinksAllowedByPlatformRole(): void
    {
        $account = (new Account())->setUsername('dashboard-only');
        $role = (new AdminRole())->setPermissionsMask(Permission::ADMIN_VIEW_DASHBOARD->value);
        $account->setAdmin((new Admin())->setAccount($account)->setAdminRole($role));
        $client = $this->clientFor($account, [
            Account::class => 1, Organisation::class => 1, Project::class => 1,
        ]);
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('nav a[href="/admin/notifications"]');
        self::assertSelectorExists('nav a[href="/admin/events"]');
        foreach (['accounts', 'organizations', 'logs', 'statuses', 'settings', 'social/manage-staff'] as $path) {
            self::assertSelectorNotExists(sprintf('nav a[href="/admin/%s"]', $path));
        }
    }

    public static function landingPages(): iterable
    {
        yield 'statuses' => ['/admin/statuses', 'ProjectApplication'];
    }

    public static function adminReadRoutes(): iterable
    {
        foreach (['', '/accounts', '/organizations', '/logs', '/statuses', '/notifications', '/settings',
            '/social/manage-staff', '/events', '/api/logs/system', '/api/logs/audit',
            '/accounts/modify/test', '/organizations/create', '/organizations/modify/test',
            '/events/create', '/events/modify/test'] as $path) {
            yield '/admin'.$path => ['/admin'.$path];
        }
    }

    #[DataProvider('adminReadRoutes')]
    public function testAdminReadRoutesRejectOrdinaryAccounts(string $path): void
    {
        $client = $this->clientFor((new Account())->setUsername('ordinary'));
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
    }

    public static function adminDestinations(): iterable
    {
        foreach (self::adminReadRoutes() as $name => [$path]) {
            if (!in_array($path, ['/admin', '/admin/accounts', '/admin/statuses'], true)) {
                yield $name => [$path];
            }
        }
    }

    #[DataProvider('adminDestinations')]
    public function testAdminDestinationsOpenForPlatformAdministrator(string $path): void
    {
        $client = $this->clientFor($this->platformAdministrator());
        foreach ([\App\Repository\Log\SystemLogRepository::class, \App\Repository\Log\AuditLogRepository::class] as $class) {
            $repository = $this->createStub($class);
            $repository->method('findLatest')->willReturn([]);
            self::getContainer()->set($class, $repository);
        }
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    #[DataProvider('landingPages')]
    public function testNewDashboardLinksHaveWorkingLandingPages(string $path, string $description): void
    {
        $client = $this->clientFor($this->platformAdministrator());
        $repository = $this->createStub(\App\Repository\Common\StatusRepository::class);
        $accepted = (new \App\Entity\Common\Status())->setName('accepted')->setScope('ProjectApplication')->setColourHex('#123456');
        $repository->method('findFirstByScopeAndNames')->willReturnCallback(
            static fn (string $scope, array $names) => $scope === 'ProjectApplication' && in_array('accepted', $names, true)
                ? $accepted
                : ($scope === 'ProjectParticipant' ? (new \App\Entity\Common\Status())->setName('active') : null),
        );
        $repository->method('findBy')->willReturn([
            (new \App\Entity\Common\Status())->setName('send')->setScope('ProjectApplication')->setColourHex('#123456'),
            (new \App\Entity\Common\Status())->setName('active')->setScope('PackageTask')->setColourHex('#123456'),
        ]);
        self::getContainer()->set(\App\Repository\Common\StatusRepository::class, $repository);
        $client->catchExceptions(false);
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $description);
        self::assertSelectorTextContains('main', 'PackageTask');
        self::assertSelectorTextContains('tbody tr:first-child td:nth-child(2)', 'Ingediend');
        self::assertSelectorTextContains('tbody tr:first-child td:last-child', 'Geaccepteerd');
        self::assertSelectorExists('main a[href="/admin"]');
    }

    #[DataProvider('landingPages')]
    public function testOrdinaryAccountCannotOpenNewDashboardDestinations(string $path, string $description): void
    {
        $client = $this->clientFor((new Account())->setUsername('ordinary'));
        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAccountAdministratorCanOpenPopulatedOverview(): void
    {
        $account = (new Account())->setUsername('account-admin');
        $role = (new AdminRole())->setPermissionsMask(Permission::ADMIN_MANAGE_ACCOUNTS->value);
        $account->setAdmin((new Admin())->setAccount($account)->setAdminRole($role));
        $client = $this->clientFor($account);
        $statuses = $this->createStub(EntityRepository::class);
        $statuses->method('findBy')->willReturn([]);
        $query = $this->createStub(Query::class);
        $query->method('setParameters')->willReturnSelf();
        $query->method('setFirstResult')->willReturnSelf();
        $query->method('setMaxResults')->willReturnSelf();
        $query->method('getSingleScalarResult')->willReturn(1);
        $query->method('getArrayResult')->willReturn([[
            'username' => 'account-fixture',
            'email' => 'account-fixture@example.test',
            'firstName' => 'Test',
            'lastName' => 'Account',
            'statusName' => 'active',
        ]]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($statuses);
        $builder = new QueryBuilder($em);
        $em->method('createQuery')->willReturn($query);
        $em->method('createQueryBuilder')->willReturn($builder);
        $container = self::getContainer();
        $container->set(AccountOverview::class, new AccountOverview(
            $em,
            $container->get(AuthorizationCheckerInterface::class),
            $container->get('request_stack'),
        ));

        $client->request('GET', '/admin/accounts');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('tbody', 'account-fixture@example.test');
    }

    public function testDashboardPermissionDoesNotGrantAccessToAccounts(): void
    {
        $account = (new Account())->setUsername('dashboard-only');
        $role = (new AdminRole())->setPermissionsMask(Permission::ADMIN_VIEW_DASHBOARD->value);
        $account->setAdmin((new Admin())->setAccount($account)->setAdminRole($role));
        $client = $this->clientFor($account);
        $client->request('GET', '/admin/accounts');

        self::assertResponseStatusCodeSame(403);
    }

    private function platformAdministrator(): Account
    {
        $account = (new Account())->setUsername('a1');
        $admin = (new Admin())->setAccount($account);
        $admin->makeSuperAdmin();

        return $account->setAdmin($admin);
    }

    /** @param array<class-string, int> $totals */
    private function clientFor(Account $account, array $totals = []): KernelBrowser
    {
        $client = self::createClient();
        $accountRepository = $this->createStub(EntityRepository::class);
        $accountRepository->method('find')->willReturn($account);
        $authenticationEm = $this->createStub(EntityManagerInterface::class);
        $authenticationEm->method('getRepository')->willReturn($accountRepository);
        self::getContainer()->set(AccountUserProvider::class, new AccountUserProvider($authenticationEm));

        $em = $this->createMock(EntityManagerInterface::class);
        $repositories = [];
        foreach ($totals as $class => $total) {
            $repository = $this->createMock(EntityRepository::class);
            $repository->expects(self::once())->method('count')->with(['deletedAt' => null])->willReturn($total);
            $repositories[] = [$class, $repository];
        }
        $em->expects(self::exactly(count($totals)))->method('getRepository')->willReturnMap($repositories);
        self::getContainer()->set(DashboardTotals::class, new DashboardTotals(
            $em,
            self::getContainer()->get(AuthorizationCheckerInterface::class),
        ));
        $client->loginUser($account);

        return $client;
    }
}
