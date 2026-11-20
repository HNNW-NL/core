<?php

namespace App\Tests\Twig;

use App\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class ProjectNavigationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    #[DataProvider('routeProvider')]
    public function testOnlyTheCurrentProjectPageIsActive(string $route): void
    {
        static::bootKernel(['debug' => false]);
        $container = static::getContainer();
        $requests = $container->get(RequestStack::class);
        $requests->push(new Request(attributes: ['_route' => $route]));
        try {
            $html = $container->get(Environment::class)->render('core/org/_modify-project-nav.html.twig', [
                'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ]);
            $crawler = new Crawler($html);
            self::assertCount(6, $crawler->filter('nav a'));
            if ($route === 'org.modifyProject.updates') {
                self::assertCount(0, $crawler->filter('a.active, a[aria-current]'));
            } else {
                $expected = $container->get(UrlGeneratorInterface::class)->generate($route, ['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']);
                self::assertCount(1, $crawler->filter('a.active'));
                self::assertCount(1, $crawler->filter('a[aria-current="page"]'));
                self::assertSame($expected, $crawler->filter('a.active')->attr('href'));
            }
        } finally {
            $requests->pop();
        }
    }

    public static function routeProvider(): iterable
    {
        foreach ([
            'org.modifyProject.workPackages', 'org.modifyProject.tasks', 'org.modifyProject',
            'org.modifyProject.reviews', 'org.modifyProject.participants', 'org.modifyProject.inviteParticipants',
            'org.modifyProject.updates',
        ] as $route) {
            yield $route => [$route];
        }
    }
}
