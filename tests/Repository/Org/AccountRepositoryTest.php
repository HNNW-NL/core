<?php

namespace App\Tests\Repository\Org;

use App\Repository\Org\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AccountRepositoryTest extends KernelTestCase
{
    private ?AccountRepository $accountRepository;
    private ?EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->accountRepository =$container
            ->get(AccountRepository::class);
        $this->entityManager  = $container->get(EntityManagerInterface::class);
    }

    // findAllForAdmin

    public function testShouldReturnAccountsInDescendingOrderOfId(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // findBySearch

    public function testWhenEmailMatchesSearchShouldReturnAccountWithEmail(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenFirstNameMatchesSearchShouldReturnAccountWithFirstName(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLastNameMatchesSearchShouldReturnAccountWithLastName(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }


    // findLatestChanged

    public function testWhenMoreThanLimitExistShouldReturnLatestCreated(): void
    {
        $result = $this->accountRepository->findLatestChanged(1);

        $this->assertEquals(1,count($result));
        $this->assertEquals("TestUser",$result[0]->getUsername());
    }

    // countActive

    public function testWhenAllAccountActiveShouldReturnCountAllAccounts(): void
    {
        $result = $this->accountRepository->countActive();

        $this->assertEquals(2,$result);
    }

    public function testWhenAccountIsUnverifiedShouldNotBeCounted(): void
    {
        $account = $this->accountRepository->findOneBy(['username' => 'TestUser']);

        $account->unverifyEmail();
        $this->entityManager->flush();

        $result = $this->accountRepository->countActive();

        $this->assertEquals(1,$result);
    }

    public function testWhenAccountIsDeletedShouldNotBeCounted(): void
    {
        $account = $this->accountRepository->findOneBy(['username' => 'TestUser']);

        $account->softDelete();
        $this->entityManager->flush();

        $result = $this->accountRepository->countActive();

        $this->assertEquals(1,$result);
    }


    protected function tearDown(): void
    {
        parent::tearDown();
        $this->accountRepository = null;
        $this->entityManager = null;
    }
}
