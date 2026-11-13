<?php
namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrgSecurityTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testOrganisationPagesRequireLogin(): void
    {
        $client = static::createClient();

       foreach ([
    '/org',
    '/org/projects',
    '/org/projects/create',
    '/org/staff',
    '/org/staff/voorbeeld',
    ] as $url) {
            $client->request('GET', $url);

           self::assertSame(
    302,
    $client->getResponse()->getStatusCode(),
    "De pagina $url moet bezoekers naar de login sturen."
);

self::assertSame(
    '/auth/login',
    $client->getResponse()->headers->get('Location'),
    "De pagina $url moet naar de inlogpagina verwijzen."
);
        }
    }
}