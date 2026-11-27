<?php

namespace App\Tests\Module\Main\Controller;

use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectsControllerTest extends WebTestCase
{
    public function testSearchAndRemoteFilterArePagedOnServer(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('countPublicProjects')
            ->with('water', true, true)
            ->willReturn(21);
        $repository->expects($this->once())
            ->method('findPaginatedPublicProjects')
            ->with('water', true, 2, 20, true)
            ->willReturn([[
                'slug' => 'water-project',
                'title' => 'Water project',
                'summary' => 'A public project.',
                'organisationName' => 'Public organisation',
                'location' => null,
                'projectStatus' => 'published',
                'remotePossible' => true,
                'canApply' => true,
                'skills' => ['PHP'],
            ]]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects?q=water&remote=yes&open=1&page=2');

        $this->assertResponseIsSuccessful();
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSelectorTextContains('.project-results-count', '21 projecten gevonden');
        $this->assertSelectorTextContains('a[href="/projects/water-project"]', 'Water project');
        $this->assertSelectorExists('select[name="remote"] option[value="yes"][selected]');
        $this->assertSelectorTextContains('.project-pagination', 'Pagina 2 van 2');
    }

    public function testCombinedFiltersWithNoResultsOfferARecoveryLink(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('countPublicProjects')
            ->with('unknown', false, false)
            ->willReturn(0);
        $repository->expects($this->once())
            ->method('findPaginatedPublicProjects')
            ->with('unknown', false, 1, 20, false)
            ->willReturn([]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects?q=unknown&remote=no');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.open-projects-empty', 'Geen projecten gevonden');
        $this->assertSelectorExists('.open-projects-empty a[href="/projects"]');
        $this->assertSelectorExists('input[name="q"][value="unknown"]');
        $this->assertSelectorExists('select[name="remote"] option[value="no"][selected]');
    }

    public function testInvalidSearchFilterAndPageAreReportedAndRecovered(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('countPublicProjects')
            ->with('', false, null)
            ->willReturn(0);
        $repository->expects($this->once())
            ->method('findPaginatedPublicProjects')
            ->with('', false, 1, 20, null)
            ->willReturn([]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects?q=' . str_repeat('a', 101) . '&remote=invalid&page=0');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[role="alert"]', 'maximaal 100 tekens');
        $this->assertSelectorTextContains('[role="alert"]', 'projectfilter is ongeldig');
        $this->assertSelectorTextContains('[role="alert"]', 'paginanummer is ongeldig');
        $this->assertSelectorExists('input[name="q"][value=""]');
    }

    public function testPublicListingDoesNotRenderPrivateProjectFields(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('countPublicProjects')
            ->with('', false, null)
            ->willReturn(1);
        $repository->expects($this->once())
            ->method('findPaginatedPublicProjects')
            ->with('', false, 1, 20, null)
            ->willReturn([[
                'slug' => 'public-project',
                'title' => 'Public project',
                'summary' => 'Public summary',
                'organisationName' => 'Public organisation',
                'location' => null,
                'projectStatus' => 'published',
                'remotePossible' => false,
                'canApply' => false,
                'skills' => [],
                'applicationMotivation' => 'PRIVATE APPLICATION MOTIVATION',
                'participantEmail' => 'private@example.test',
            ]]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a[href="/projects/public-project"]', 'Public project');
        $this->assertStringNotContainsString('PRIVATE APPLICATION MOTIVATION', (string) $client->getResponse()->getContent());
        $this->assertStringNotContainsString('private@example.test', (string) $client->getResponse()->getContent());
    }
}
