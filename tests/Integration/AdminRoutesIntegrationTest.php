<?php

namespace App\Tests\Integration;

use App\Entity\Account\Account;
use App\Entity\Admin\Admin;
use App\Entity\Admin\AdminRole;
use App\Entity\Common\Event;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Kernel;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

/** INT-07: integrated admin routes use the real router, database, templates and permission voter. */
final class AdminRoutesIntegrationTest extends WebTestCase
{
    private array $created = [];

    protected static function getKernelClass(): string { return Kernel::class; }

    private function persist(EntityManagerInterface $em, object $entity): void
    {
        $this->created[] = [$entity::class, $entity->getId()];
        $em->persist($entity);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->created !== []) {
                $em = self::getContainer()->get(EntityManagerInterface::class);
                $em->clear();
                foreach (array_reverse($this->created) as [$class, $id]) {
                    if (($entity = $em->find($class, $id)) !== null) {
                        $em->remove($entity);
                        $em->flush();
                    }
                }
            }
        } finally {
            $this->created = [];
            parent::tearDown();
        }
    }

    public function testIntegratedAdminRoutesAreUsableAndRequireTheirOwnPermission(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $run = bin2hex(random_bytes(8));
        $status = (new Status())->setScope('account')->setName('int07-'.$run)->setColourHex('#1098ad');
        $role = (new AdminRole())->setName('int07-'.$run)->setPermissionsMask(0);
        $ordinary = (new Account())->setUsername('int07-user-'.$run)->setEmail('int07-user-'.$run.'@example.test')
            ->setPasswordHash('unused')->setStatus($status);
        $administrator = (new Account())->setUsername('int07-admin-'.$run)->setEmail('int07-admin-'.$run.'@example.test')
            ->setPasswordHash('unused')->setStatus($status);
        $admin = (new Admin())->setAccount($administrator)->setAdminRole($role)->setStatus($status);
        $administrator->setAdmin($admin);
        $organisation = (new Organisation())->setName('INT-07 '.$run)->setSlug('int07-'.$run)->setStatus($status);
        $event = (new Event())->setTitle('INT-07 '.$run)->setSlug('int07-'.$run)->setOrganisation($organisation)
            ->setAuthorAccount($administrator)->setStartDatetime(new \DateTimeImmutable());
        foreach ([$status, $role, $ordinary, $administrator, $admin, $organisation, $event] as $entity) {
            $this->persist($em, $entity);
        }
        $em->flush();

        $router = self::getContainer()->get('router');
        $routes = [
            'admin.home' => [Permission::ADMIN_VIEW_DASHBOARD, []],
            'admin.accounts.home' => [Permission::ADMIN_MANAGE_ACCOUNTS, []],
            'admin.accounts.create' => [Permission::ADMIN_MANAGE_ACCOUNTS, []],
            'admin.accounts.view' => [Permission::ADMIN_MANAGE_ACCOUNTS, ['id' => (string) $ordinary->getId()]],
            'admin.accounts.modify' => [Permission::ADMIN_MANAGE_ACCOUNTS, ['id' => (string) $ordinary->getId()]],
            'admin.organisations.home' => [Permission::ADMIN_MANAGE_ORGANISATIONS, []],
            'admin.organisations.create' => [Permission::ADMIN_MANAGE_ORGANISATIONS, []],
            'admin.organisations.modify' => [Permission::ADMIN_MANAGE_ORGANISATIONS, ['id' => (string) $organisation->getId()]],
            'admin.logs' => [Permission::ADMIN_VIEW_LOGS, []],
            'admin.api.logs.system' => [Permission::ADMIN_VIEW_LOGS, []],
            'admin.api.logs.audit' => [Permission::ADMIN_VIEW_LOGS, []],
            'admin.statuses' => [Permission::ADMIN_MANAGE_SETTINGS, []],
            'admin.settings' => [Permission::ADMIN_MANAGE_SETTINGS, []],
            'admin.notifications' => [Permission::ADMIN_VIEW_DASHBOARD, []],
            'admin.social.manageStaff' => [Permission::ADMIN_MANAGE_STAFF, []],
            'admin.events.home' => [Permission::ADMIN_VIEW_DASHBOARD, []],
            'admin.events.create' => [Permission::ADMIN_VIEW_DASHBOARD, []],
            'admin.events.modify' => [Permission::ADMIN_VIEW_DASHBOARD, ['id' => (string) $event->getId()]],
        ];
        // New admin endpoints must be added to this integration contract explicitly.
        foreach ($router->getRouteCollection() as $name => $route) {
            if (str_starts_with($route->getPath(), '/admin') && in_array('GET', $route->getMethods(), true)) {
                self::assertArrayHasKey($name, $routes, $route->getPath());
            }
        }
        foreach ($routes as $name => [$permission, $parameters]) {
            $path = $router->generate($name, $parameters);
            $client->request('GET', $path);
            self::assertResponseRedirects('/auth/login');
        }
        foreach ([$ordinary, $administrator] as $user) {
            $client->loginUser($user);
            foreach ($routes as $name => [$permission, $parameters]) {
                $client->request('GET', $router->generate($name, $parameters));
                self::assertResponseStatusCodeSame(403);
            }
        }
        foreach ($routes as $name => [$permission, $parameters]) {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $em->find(AdminRole::class, $role->getId())->setPermissionsMask($permission->value);
            $em->flush();
            $client->loginUser($em->find(Account::class, $administrator->getId()));
            $client->request('GET', $router->generate($name, $parameters));
            self::assertResponseIsSuccessful();
            if ($name === 'admin.organisations.home' || $name === 'admin.events.home') {
                self::assertSelectorTextContains('main', 'INT-07 '.$run);
            }
        }
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->find(AdminRole::class, $role->getId())->setPermissionsMask(Permission::maskFor([
            Permission::ADMIN_MANAGE_ACCOUNTS, Permission::ADMIN_MANAGE_SETTINGS, Permission::ADMIN_MANAGE_ORGANISATIONS,
            Permission::ADMIN_VIEW_DASHBOARD,
        ]));
        $em->flush();
        $client->loginUser($em->find(Account::class, $administrator->getId()));
        foreach (['admin.accounts.modify', 'admin.accounts.view', 'admin.organisations.modify', 'admin.events.modify'] as $name) {
            foreach (['invalid-id', (string) new Ulid()] as $id) {
                $client->request('GET', $router->generate($name, ['id' => $id]));
                self::assertResponseStatusCodeSame(404);
            }
        }
        foreach ([
            ['admin.accounts.delete', ['id' => (string) $ordinary->getId()]],
            ['admin.statuses.save', []],
            ['admin.statuses.delete', ['id' => (string) $status->getId()]],
            ['admin.statuses.transitions', ['id' => (string) $status->getId()]],
        ] as [$name, $parameters]) {
            $client->request('POST', $router->generate($name, $parameters), ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(403);
        }
    }
}
