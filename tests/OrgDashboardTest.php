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
}