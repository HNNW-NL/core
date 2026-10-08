<?php

namespace App\Tests\Repository\Log;

use App\Repository\Log\SystemLogRepository;
use DateTimeImmutable;
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

    // findByCursor

    public function testWhenMoreThanLimitExistShouldReturnOrderedByLatestCreatedAndOrderedById(): void
    {
        $result = $this->systemLogRepository->findByCursor();

        $this->assertEquals(20,count($result));
        $this->assertEquals("A system message",$result[0]->getMessage());
    }

    public function testWhenStartDateTodayShouldReturnNoResults(): void
    {
        $result = $this->systemLogRepository->findByCursor(null,null,new DateTimeImmutable());

        $this->assertEquals(0,count($result));
    }

    public function testWhenLevelHasNoSystemLogsShouldReturnNoResults(): void
    {
        $result = $this->systemLogRepository->findByCursor(null,'level5');

        $this->assertEquals(0,count($result));
    }

    public function testWhenLevelHasSystemLogsShouldReturnResults(): void
    {
        $result = $this->systemLogRepository->findByCursor(null,'1');

        $this->assertEquals(20,count($result));
    }

    public function testWhenUsingCursorShouldReturnNextResults(): void
    {
        $cursor = $this->systemLogRepository->findBy(['message' => 'A system message'])[0];
        $result = $this->systemLogRepository->findByCursor($cursor,null,null,null,true,1);

        $this->assertEquals(1,count($result));
        $this->assertEquals('A second system log',$result[0]->getMessage());
    }

    public function testWhenUsingCursorwithForwardFalseShouldReturnPreviousResults(): void
    {
        $cursor = $this->systemLogRepository->findBy(['message' => 'A second system log'])[0];
        $result = $this->systemLogRepository->findByCursor($cursor,null,null,null,false,1);

        $this->assertEquals(1,count($result));
        $this->assertEquals('A system message',$result[0]->getMessage());
    }

    // hasNext

    public function testWhenNextCursorExistShouldTrue(): void
    {
        $cursor = $this->systemLogRepository->findBy(['message' => 'A second system log'])[0];
        $result = $this->systemLogRepository->hasNext($cursor,null,null,null);

        $this->assertTrue($result);
    }

    public function testWhenNextCursorDoesntExistShouldFalse(): void
    {
        $cursor = $this->systemLogRepository->findBy(['message' => 'system log 20'])[0];
        $result = $this->systemLogRepository->hasNext($cursor,null,null,null);

        $this->assertFalse($result);
    }

    // hasPrev

    public function testWhenPrevCursorExistShouldTrue(): void
    {
        $cursor = $this->systemLogRepository->findBy(['message' => 'A second system log'])[0];
        $result = $this->systemLogRepository->hasPrev($cursor,null,null,null);

        $this->assertTrue($result);
    }

    public function testWhenPrevCursorDoesntExistShouldFalse(): void
    {
        $cursor = $this->systemLogRepository->findBy(['message' => 'A system message'])[0];
        $result = $this->systemLogRepository->hasPrev($cursor,null,null,null);

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->systemLogRepository = null;
    }
}
