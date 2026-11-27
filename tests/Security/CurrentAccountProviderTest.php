<?php

namespace App\Tests\Security;

use App\Entity\Account\Account;
use App\Security\CurrentAccountProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final class CurrentAccountProviderTest extends TestCase
{
    public function testReturnsSymfonyAccount(): void
    {
        $account = new Account();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($account);

        self::assertSame($account, (new CurrentAccountProvider($security))->getAccount());
    }

    public function testReturnsNullWithoutSymfonyUser(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        self::assertNull((new CurrentAccountProvider($security))->getAccount());
    }

    public function testReturnsNullForOtherSymfonyUserTypes(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->createStub(UserInterface::class));

        self::assertNull((new CurrentAccountProvider($security))->getAccount());
    }
}
