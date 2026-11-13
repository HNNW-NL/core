<?php

namespace App\Tests\Security;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Uid\Uuid;

final class LegacyAccountSessionTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testLegacyAccountIdDoesNotGrantAccessToSettings(): void
    {
        $client = static::createClient();
        $session = static::getContainer()->get('session.factory')->createSession();
        $accountId = Uuid::v7()->toRfc4122();
        $session->set('account_id', $accountId);
        $session->save();
        $client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));

        $client->request('GET', '/account/settings');

        self::assertSame($accountId, $client->getRequest()->getSession()->get('account_id'));
        self::assertResponseRedirects('/auth/login');
    }
}
