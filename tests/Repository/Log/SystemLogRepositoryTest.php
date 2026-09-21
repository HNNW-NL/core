<?php

namespace App\Tests\Repository\Log;

use App\Repository\Log\SystemLogRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SystemLogRepositoryTest extends KernelTestCase
{

    private ?SystemLogRepository $systemLogRepository;

    protected function setUp(): void
    {
        $this->systemLogRepository = static::getContainer()
            ->get(SystemLogRepository::class);
    }

    // findLatest

    public function testWhenMoreThanLimitExistShouldReturnLatestCreated(): void
    {
        $result = $this->systemLogRepository->findLatest(1);

        $this->assertEquals(1,count($result));
        // because systemlogs are made in the same second they have the same time so the first one created is the latest
        $this->assertEquals("A system message",$result[0]->getMessage());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->systemLogRepository = null;
    }
}
