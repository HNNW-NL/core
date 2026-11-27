<?php

namespace App\Tests;

use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MainControllerTest extends WebTestCase
{
    public function testLoggedOutHomepageShowsLoginWithoutAccountDataAndIsNotSharedCached(): void
    {
        $client = static::createClient();
        $this->mockHomepageProjects([]);
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/auth/login"]');
        $this->assertSelectorNotExists('a[href="/account"]');
        $this->assertStringNotContainsString('TestUser', $crawler->text());
        $this->assertNoStoreResponse($client->getResponse());
    }

    public function testLoggedInHomepageShowsAccountAndLogoutLinks(): void
    {
        $client = static::createClient();
        $this->mockHomepageProjects([]);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_USER')
            ->willReturn(true);
        static::getContainer()->set('security.authorization_checker', $authorizationChecker);

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/account"]');
        $this->assertSelectorExists('a[href="/auth/logout"]');
        $this->assertSelectorNotExists('a[href="/auth/login"]');
        $this->assertNoStoreResponse($client->getResponse());
    }

    public function testShouldDisplayAbout(): void
    {
        $client = static::createClient();
        $client->request('GET', '/about');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Samenwerken');
    }

    private function mockHomepageProjects(array $projects): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findPublicProjects')
            ->with(6)
            ->willReturn($projects);
        static::getContainer()->set(ProjectRepository::class, $repository);
    }

    private function assertNoStoreResponse(\Symfony\Component\HttpFoundation\Response $response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }
}
