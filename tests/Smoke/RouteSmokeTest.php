<?php

namespace App\Tests\Smoke;

use App\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RouteSmokeTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('routeProvider')]
    public function testRouteDoesNotCrash(
        string $area,
        string $path,
        array $expectedStatuses
    ): void {
        $client = static::createClient();

        $client->request('GET', $path);

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertContains(
            $statusCode,
            $expectedStatuses,
            sprintf(
                '%s smoke test failed for "%s". Received HTTP %d.',
                $area,
                $path,
                $statusCode
            )
        );
    }

    public static function routeProvider(): array
    {
        return [
            // Account
            'account home' => [
                'Account',
                '/account',
                [200, 302, 303],
            ],
            'account settings' => [
                'Account',
                '/account/settings',
                [200, 302, 303],
            ],
            'account projects' => [
                'Account',
                '/account/projects',
                [200, 302, 303],
            ],

            // Project
            'projects overview' => [
                'Project',
                '/projects',
                [200],
            ],
            'project detail' => [
                'Project',
                '/projects/Test-Project',
                [200],
            ],

            // Organisation
            'organisation home' => [
                'Organisation',
                '/org',
                [200, 302, 303, 403],
            ],
            'organisation projects' => [
                'Organisation',
                '/org/projects',
                [200, 302, 303, 403],
            ],

            // Work packages
            'project work packages' => [
                'Work packages',
                '/projects/Test-Project/work-packages',
                [200, 302, 303, 403],
            ],
            'project tasks' => [
                'Work packages',
                '/projects/Test-Project/tasks',
                [200, 302, 303, 403],
            ],
        ];
    }
}
