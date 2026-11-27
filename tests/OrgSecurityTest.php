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
    public function testGebruikerZonderLidmaatschapZietGeenOrganisatie(): void
{
    $client = static::createClient();

    $em = static::getContainer()->get(
        \Doctrine\ORM\EntityManagerInterface::class
    );

    $account = $em->getRepository(\App\Entity\Account\Account::class)
        ->findOneBy(['username' => 'AdminUser']);

    self::assertNotNull($account);
    self::assertFalse($account->getAdmin()?->isSuperAdmin() ?? false);

    $organisatie = $em->getRepository(\App\Entity\Org\Organisation::class)
        ->findOneBy(['name' => 'Test Organistion']);

    self::assertNotNull($organisatie);

    $client->loginUser($account);
    $client->request('GET', '/org');

    self::assertResponseIsSuccessful();
    self::assertStringContainsString(
        'Je bent nog niet gekoppeld',
        $client->getResponse()->getContent()
    );

    // Een organisatie van iemand anders mag niet gekozen worden.
    $client->request(
        'GET',
        '/org?organisation=' . $organisatie->getId()->toRfc4122()
    );

    self::assertResponseStatusCodeSame(404);
    // Zonder organisatierechten mag je ook geen projecten bekijken.
$client->request(
    'GET',
    '/org/projects?organisation=' . $organisatie->getId()->toRfc4122()
);

self::assertResponseStatusCodeSame(403);
// Zonder medewerkersrechten mag je de medewerkerspagina niet openen.
$client->request(
    'GET',
    '/org/staff?organisation=' . $organisatie->getId()->toRfc4122()
);

self::assertResponseStatusCodeSame(403);
}
}
