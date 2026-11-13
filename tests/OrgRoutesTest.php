<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OrgRoutesTest extends KernelTestCase
{
        protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testOrganisationRoutesExist(): void
    {
        self::bootKernel();

        $routes = self::getContainer()
            ->get('router')
            ->getRouteCollection();

        self::assertSame('/org', $routes->get('org.home')?->getPath());

        self::assertSame(
            '/org/projects',
            $routes->get('org.projects')?->getPath()
        );

        self::assertSame(
            '/org/staff',
            $routes->get('org.staff')?->getPath()
        );
    }
}