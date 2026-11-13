<?php

namespace App\Tests\Security;

use App\Entity\Account\Account;
use App\Security\AccountUserProvider;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class AccountUserProviderTest extends TestCase
{
    public function testLoadsAccountByUsername(): void
    {
        $account = (new Account())
            ->setUsername('MichaelHNNWTest')
            ->setEmail('michael@hnnw.nl');
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['username' => 'MichaelHNNWTest'])
            ->willReturn($account);

        $provider = new AccountUserProvider($this->registryWithRepository($repository));

        self::assertSame($account, $provider->loadUserByIdentifier('MichaelHNNWTest'));
    }

    public function testLoadsAccountByEmail(): void
    {
        $account = (new Account())
            ->setUsername('MichaelHNNWTest')
            ->setEmail('michael@hnnw.nl');
        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'michael@hnnw.nl'])
            ->willReturn($account);

        $provider = new AccountUserProvider($this->registryWithRepository($repository));

        self::assertSame($account, $provider->loadUserByIdentifier('michael@hnnw.nl'));
    }

    private function registryWithRepository(ObjectRepository $repository): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())
            ->method('getRepository')
            ->with(Account::class)
            ->willReturn($repository);

        return $registry;
    }
}
