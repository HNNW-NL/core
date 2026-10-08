<?php

namespace App\Tests\Repository\Log;

use App\Repository\Log\AuditLogRepository;
use App\Repository\Org\AccountRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditLogRepositoryTest extends KernelTestCase
{
    private ?AuditLogRepository $auditLogRepository;
    private ?AccountRepository $accountRepository;

    protected function setUp(): void
    {
        $this->auditLogRepository = static::getContainer()
            ->get(AuditLogRepository::class);
        $this->accountRepository = static::getContainer()
            ->get(AccountRepository::class);
    }

    // findLatest

    public function testWhenMoreThanLimitExistShouldReturnLatestCreated(): void
    {
        $result = $this->auditLogRepository->findLatest(1);

        $this->assertEquals(1,count($result));
        $this->assertEquals("login",$result[0]->getAction());
    }

    // findAuditLogByCursor

    public function testWhenMoreThanLimitExistShouldReturnOrderedByLatestCreatedAndOrderedById(): void
    {
        $result = $this->auditLogRepository->findAuditLogByCursor();

        $this->assertEquals(20,count($result));
        $this->assertEquals("login",$result[0]->getAction());
    }

    public function testWhenStartDateTodayShouldReturnNoResults(): void
    {
        $result = $this->auditLogRepository->findAuditLogByCursor(null,null,new DateTimeImmutable());

        $this->assertEquals(0,count($result));
    }

    public function testWhenActorHasNoAuditLogsShouldReturnNoResults(): void
    {
        $actor = $this->accountRepository->findBy(['username' => 'AdminUser'])[0];
        $result = $this->auditLogRepository->findAuditLogByCursor(null,$actor);

        $this->assertEquals(0,count($result));
    }

    public function testWhenActorHasAuditLogsShouldReturnResults(): void
    {
        $actor = $this->accountRepository->findBy(['username' => 'TestUser'])[0];
        $result = $this->auditLogRepository->findAuditLogByCursor(null,$actor);

        $this->assertEquals(20,count($result));
    }

    public function testWhenUsingCursorShouldReturnNextResults(): void
    {
        $cursor = $this->auditLogRepository->findBy(['action' => 'login'])[0];
        $result = $this->auditLogRepository->findAuditLogByCursor($cursor,null,null,null,true,5);

        $this->assertEquals(5,count($result));
        $this->assertEquals('test',$result[0]->getAction());
    }

    public function testWhenUsingCursorwithForwardFalseShouldReturnPreviousResults(): void
    {
        $cursor = $this->auditLogRepository->findBy(['action' => 'test5'])[0];
        $result = $this->auditLogRepository->findAuditLogByCursor($cursor,null,null,null,false,5);

        $this->assertEquals(5,count($result));
        $this->assertEquals('login',$result[0]->getAction());
    }

    // hasNext

    public function testWhenNextCursorExistShouldTrue(): void
    {
        $cursor = $this->auditLogRepository->findBy(['action' => 'test5'])[0];
        $result = $this->auditLogRepository->hasNext($cursor,null,null,null);

        $this->assertTrue($result);
    }

    public function testWhenNextCursorDoesntExistShouldFalse(): void
    {
        $cursor = $this->auditLogRepository->findBy(['action' => 'test20'])[0];
        $result = $this->auditLogRepository->hasNext($cursor,null,null,null);

        $this->assertFalse($result);
    }

    // hasPrev

    public function testWhenPrevCursorExistShouldTrue(): void
    {
        $cursor = $this->auditLogRepository->findBy(['action' => 'test5'])[0];
        $result = $this->auditLogRepository->hasPrev($cursor,null,null,null);

        $this->assertTrue($result);
    }

    public function testWhenPrevCursorDoesntExistShouldFalse(): void
    {
        $cursor = $this->auditLogRepository->findBy(['action' => 'login'])[0];
        $result = $this->auditLogRepository->hasPrev($cursor,null,null,null);

        $this->assertFalse($result);
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        $this->auditLogRepository = null;
        $this->accountRepository = null;
    }
}
