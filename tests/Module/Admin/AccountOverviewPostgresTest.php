<?php

namespace App\Tests\Module\Admin;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Module\Admin\Service\AccountOverview;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccountOverviewPostgresTest extends TestCase
{
    public function testSearchStatusAndPaginationAgainstPostgres(): void
    {
        $url = getenv('ACCOUNT_OVERVIEW_TEST_DATABASE_URL');
        if ($url === false || $url === '') {
            self::markTestSkipped('Set ACCOUNT_OVERVIEW_TEST_DATABASE_URL to a local PostgreSQL database with the application schema.');
        }
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($url);
        self::assertSame('pdo_pgsql', $params['driver'] ?? null);
        self::assertContains($params['host'] ?? null, ['127.0.0.1', 'localhost', '::1']);

        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }
        $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3).'/src/Entity']);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $db = DriverManager::getConnection($params);
        $em = new EntityManager($db, $config);
        $db->beginTransaction();
        try {
            $prefix = 'ad02-'.bin2hex(random_bytes(6));
            $wanted = (new Status())->setName($prefix)->setScope('account')->setColourHex('#123456');
            $other = (new Status())->setName($prefix.'-other')->setScope('account')->setColourHex('#123456');
            $empty = (new Status())->setName($prefix.'-empty')->setScope('account')->setColourHex('#123456');
            foreach ([$wanted, $other, $empty] as $status) {
                $em->persist($status);
            }

            for ($index = 1; $index <= 23; ++$index) {
                $name = sprintf('%s-%02d', $index === 23 ? 'outside-'.bin2hex(random_bytes(6)) : $prefix, $index);
                $email = $name.'@example.test';
                $em->persist((new Account())->setUsername($name)->setEmail($email)->setPasswordHash('test-only')
                    ->setStatus($index === 22 ? $other : $wanted));
            }
            $em->flush();

            $authorization = $this->createStub(AuthorizationCheckerInterface::class);
            $authorization->method('isGranted')->willReturn(true);
            $stack = new RequestStack();
            $request = Request::create('/admin/accounts');
            $stack->push($request);
            $overview = new AccountOverview($em, $authorization, $stack);

            $request->query->set('status', (string) $empty->getId());
            self::assertSame([], $overview->load()['items']);
            self::assertSame(0, $overview->load()['total']);

            $request->query->set('status', (string) $wanted->getId());
            self::assertSame(22, $overview->load()['total']);

            $request->query->remove('status');
            $request->query->set('q', strtoupper($prefix.'-01@example.test'));
            self::assertSame([$prefix.'-01'], array_column($overview->load()['items'], 'username'));

            $request->query->set('q', $prefix);
            $request->query->set('status', (string) $wanted->getId());
            $first = $overview->load();
            self::assertSame(21, $first['total']);
            self::assertSame(2, $first['pages']);
            self::assertCount(20, $first['items']);

            $request->query->set('page', '2');
            $second = $overview->load();
            self::assertSame(2, $second['page']);
            self::assertSame([$prefix.'-21'], array_column($second['items'], 'username'));
            self::assertSame($first['search'], $second['search']);
            self::assertSame($first['status'], $second['status']);
        } finally {
            $db->rollBack();
            $db->close();
        }
    }

    public function testDeniedBeforeDatabaseAccess(): void
    {
        $authorization = $this->createStub(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, null, true],
            ['ADMIN_MANAGE_ACCOUNTS', null, null, false],
        ]);
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $em->expects(self::never())->method('createQueryBuilder');

        $this->expectException(AccessDeniedException::class);
        (new AccountOverview($em, $authorization, new RequestStack()))->load();
    }
}
