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
use App\Security\AccountUserProvider;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
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
        foreach (self::availableDestinations() as [$path]) {
            self::assertSelectorExists(sprintf('nav a[href="/admin/%s"]', $path));
        }
        self::assertSelectorTextContains('nav', 'Accounts Momenteel niet beschikbaar');
        self::assertSelectorTextContains('nav', 'Statussen Momenteel niet beschikbaar');
        self::assertSelectorNotExists('nav a[href="/admin/accounts"], nav a[href="/admin/statuses"]');
        if ($accounts === 0 && $organisations === 0 && $projects === 0) {
            self::assertSelectorTextContains('#dashboard-empty', 'Er zijn nog geen');
        }
    }

    public static function availableDestinations(): iterable
    {
        foreach (['organizations', 'logs', 'notifications', 'settings', 'social/manage-staff', 'events'] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('availableDestinations')]
    public function testAvailableDestinationOpensForPlatformAdministrator(string $path): void
    {
        $client = $this->clientFor($this->platformAdministrator());
        $client->request('GET', '/admin/'.$path);

        self::assertResponseIsSuccessful();
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
        $em = $this->createMock(EntityManagerInterface::class);
        $repositories = [];
        foreach ([Account::class, Organisation::class, Project::class] as $class) {
            $repository = $this->createMock(EntityRepository::class);
            $repository->expects(isset($totals[$class]) ? self::once() : self::never())
                ->method('count')->with(['deletedAt' => null])->willReturn($totals[$class] ?? 0);
            if ($class === Account::class) {
                $repository->method('find')->willReturn($account);
            }
            $repositories[] = [$class, $repository];
        }
        $em->expects(self::atLeastOnce())->method('getRepository')->willReturnMap($repositories);
        self::getContainer()->set(AccountUserProvider::class, new AccountUserProvider($em));
        self::getContainer()->set(DashboardTotals::class, new DashboardTotals(
            $em,
            self::getContainer()->get(AuthorizationCheckerInterface::class),
        ));
        $client->loginUser($account);

        return $client;
    }
}
