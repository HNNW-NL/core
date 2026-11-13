<?php

namespace App\Tests\Security;

use App\Entity\Account\Account;
use App\Security\CurrentAccountProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CurrentAccountProviderTest extends TestCase
{
    public function testSymfonyAccountTakesPrecedenceOverLegacySession(): void
    {
        $account = new Account();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($account);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $provider = new CurrentAccountProvider($security, $this->legacyRequest(new Account()), $entityManager);

        self::assertSame($account, $provider->getAccount());
    }

    public function testLegacySessionResolvesAccountWithoutSymfonyUser(): void
    {
        $account = new Account();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('find')
            ->with(Account::class, self::equalTo($account->getId()))
            ->willReturn($account);

        $provider = new CurrentAccountProvider($this->createStub(Security::class), $this->legacyRequest($account), $entityManager);

        self::assertSame($account, $provider->getAccount());
    }

    public function testRequestWithoutSessionRemainsAnonymous(): void
    {
        $requests = new RequestStack();
        $requests->push(new Request());
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('find');

        $provider = new CurrentAccountProvider($this->createStub(Security::class), $requests, $entityManager);

        self::assertNull($provider->getAccount());
    }

    private function legacyRequest(Account $account): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('account_id', $account->getId()->toRfc4122());
        $request = new Request();
        $request->setSession($session);
        $requests = new RequestStack();
        $requests->push($request);

        return $requests;
    }
}
