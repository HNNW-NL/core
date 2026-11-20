<?php

namespace App\Tests;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Org\OrgMember;
use App\Entity\Org\OrgRole;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrgContextTest extends WebTestCase
{
        protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }
    public function testWisselenTussenTweeOrganisaties(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Bestaande testgebruiker en organisatie ophalen.
        $account = $em->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        $organisatieEen = $em->getRepository(Organisation::class)
            ->findOneBy(['name' => 'Test Organistion']);

        $status = $em->getRepository(Status::class)
            ->findOneBy([
                'name' => 'active',
                'scope' => 'organisation',
            ]);

        self::assertNotNull($account);
        self::assertNotNull($organisatieEen);
        self::assertNotNull($status);

        // Tijdelijk een tweede organisatie maken.
        $organisatieTwee = new Organisation();
        $organisatieTwee->setName('OR01 Tweede Testorganisatie');
        $organisatieTwee->setSlug('or01-tweede-testorganisatie');
        $organisatieTwee->setStatus($status);
        $em->persist($organisatieTwee);

        // Deze rol heeft alleen kijkrechten.
        $rol = new OrgRole();
        $rol->setName('Lezer');
        $rol->setOrganisation($organisatieTwee);
        $rol->setPermissionsMask(
            Permission::maskFor([Permission::ORG_VIEW])
        );
        $em->persist($rol);

        // TestUser mag ook de tweede organisatie bekijken.
        $lidmaatschap = new OrgMember();
        $lidmaatschap->setAccount($account);
        $lidmaatschap->setOrganisation($organisatieTwee);
        $lidmaatschap->setOrgRole($rol);
        $em->persist($lidmaatschap);

        $em->flush();

        $idEen = $organisatieEen->getId()->toRfc4122();
        $idTwee = $organisatieTwee->getId()->toRfc4122();

        $client->loginUser($account);

        // Eerste organisatie selecteren.
        $client->request('GET', '/org?organisation=' . $idEen);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'section h2',
            'Test Organistion'
        );

        self::assertStringContainsString(
            'Aantal projecten: 1',
            $client->getResponse()->getContent()
        );

        self::assertStringContainsString(
            '/org/projects?organisation=' . $idEen,
            $client->getResponse()->getContent()
        );

        // Wisselen naar de tweede organisatie.
        $client->request('GET', '/org?organisation=' . $idTwee);

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains(
            'section h2',
            'OR01 Tweede Testorganisatie'
        );

        self::assertSelectorExists(
            'option[value="' . $idTwee . '"][selected]'
        );

        $html = $client->getResponse()->getContent();

        self::assertStringContainsString('Aantal projecten: 0', $html);
        self::assertStringContainsString('Lezer', $html);

        // Een gebruiker met alleen ORG_VIEW ziet geen beheerlinks.
        self::assertStringNotContainsString(
            '/org/projects?organisation=' . $idTwee,
            $html
        );

        self::assertStringNotContainsString(
            '/org/staff?organisation=' . $idTwee,
            $html
        );
    }
}