<?php

namespace App\Tests\Repository\Account;

use App\Repository\Account\ProfileRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProfileRepositoryTest extends KernelTestCase
{
    private ?ProfileRepository $profileRepository;

    protected function setUp(): void
    {
        $this->profileRepository = static::getContainer()
            ->get(ProfileRepository::class);
    }

    // findLatest

    public function testWhenMoreThanLimitExistShouldReturnLatestCreated(): void
    {
        $result = $this->profileRepository->findLatestChanged(1);

        $this->assertEquals(1,count($result));
        $this->assertEquals("Test",$result[0]->getFirstName());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->profileRepository = null;
    }
}
