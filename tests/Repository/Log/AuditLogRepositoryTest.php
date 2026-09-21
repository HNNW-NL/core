<?php

namespace App\Tests\Repository\Log;

use App\Repository\Log\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuditLogRepositoryTest extends KernelTestCase
{
    private ?AuditLogRepository $auditLogRepository;

    protected function setUp(): void
    {
        $this->auditLogRepository = static::getContainer()
            ->get(AuditLogRepository::class);
    }

    // findLatest

    public function testWhenMoreThanLimitExistShouldReturnLatestCreated(): void
    {
        $result = $this->auditLogRepository->findLatest(1);

        $this->assertEquals(1,count($result));
        $this->assertEquals("login",$result[0]->getAction());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->auditLogRepository = null;
    }
}
