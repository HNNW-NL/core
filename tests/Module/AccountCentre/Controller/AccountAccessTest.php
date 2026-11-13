<?php

namespace App\Tests\Module\AccountCentre\Controller;

use App\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountAccessTest extends WebTestCase
{
    protected static function getKernelClass(): string { return Kernel::class; }

    public static function privatePages(): iterable
    {
        foreach (['', '/applications', '/projects', '/messages', '/settings', '/modify', '/notifications'] as $path) {
            yield $path ?: 'dashboard' => ['/account'.$path];
        }
    }

    #[DataProvider('privatePages')]
    public function testAnonymousVisitorsAreRedirected(string $path): void
    {
        self::createClient()->request('GET', $path);
        self::assertResponseRedirects('/auth/login');
    }
}
