<?php

namespace App\Tests\Module\Main\Controller;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testProfileShortcutRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');
        self::assertResponseRedirects('/auth/login');
    }

    public function testUnknownPublicProfileDoesNotExposeProfileContent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile/missing-'.bin2hex(random_bytes(8)));
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorNotExists('#profile, .profile-biography');
    }
}
