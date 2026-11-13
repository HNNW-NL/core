<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrgDashboardTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testDashboardToontOrganisatiegegevens(): void
    {
        self::bootKernel();

        $organisatie = [
            'id' => 'test-123',
            'naam' => 'Testorganisatie',
            'rollen' => ['Beheerder'],
            'aantalProjecten' => 2,
            'aantalNietBeoordeeld' => 1,
        ];

        $html = self::getContainer()->get('twig')->render(
            'pages/org/index.html.twig',
            [
                'organisaties' => [$organisatie],
                'actieveOrganisatie' => $organisatie,
            ]
        );

        self::assertStringContainsString('Testorganisatie', $html);
        self::assertStringContainsString('Beheerder', $html);
        self::assertStringContainsString('Aantal projecten: 2', $html);
        self::assertStringContainsString('Niet-beoordeelde aanvragen: 1', $html);
    }

    public function testDashboardZonderOrganisatie(): void
    {
        self::bootKernel();

        $html = self::getContainer()->get('twig')->render(
            'pages/org/index.html.twig',
            [
                'organisaties' => [],
                'actieveOrganisatie' => null,
            ]
        );

        self::assertStringContainsString(
            'Je bent nog niet gekoppeld',
            $html
        );

        self::assertStringNotContainsString(
            'Organisatienavigatie',
            $html
        );
    }
    public function testDashboardMetTweeOrganisaties(): void
    {
        self::bootKernel();

        $organisatieEen = [
            'id' => 'org-1',
            'naam' => 'Organisatie Een',
            'rollen' => ['Medewerker'],
            'aantalProjecten' => 1,
            'aantalNietBeoordeeld' => 0,
        ];

        $organisatieTwee = [
            'id' => 'org-2',
            'naam' => 'Organisatie Twee',
            'rollen' => ['Beheerder'],
            'aantalProjecten' => 3,
            'aantalNietBeoordeeld' => 2,
        ];

        $html = self::getContainer()->get('twig')->render(
            'pages/org/index.html.twig',
            [
                'organisaties' => [$organisatieEen, $organisatieTwee],
                'actieveOrganisatie' => $organisatieTwee,
            ]
        );

        self::assertStringContainsString('Kies je organisatie:', $html);
        self::assertStringContainsString('Organisatie Een', $html);
        self::assertStringContainsString('Organisatie Twee', $html);
        self::assertMatchesRegularExpression(
            '/<option value="org-2"\s+selected\s*>/',
            $html
        );
        self::assertStringContainsString('Aantal projecten: 3', $html);
    }
}