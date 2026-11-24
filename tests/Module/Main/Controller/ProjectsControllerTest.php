<?php

namespace App\Tests\Module\Main\Controller;

use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectsControllerTest extends WebTestCase
{
    public function testSearchAndRemoteFilterArePagedOnServerAndLinkToMatchingProject(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findPublicPage')
            ->with('water', true, 2, 12)
            ->willReturn([
                'items' => [[
                    'title' => 'Water project',
                    'summary' => 'Working on water quality.',
                    'slug' => 'water-project',
                    'remotePossible' => true,
                ]],
                'total' => 13,
                'page' => 2,
                'pages' => 2,
            ]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $crawler = $client->request('GET', '/projects?q=water&remote=yes&page=2');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        $this->assertSelectorTextContains('h2 a[href="/projects/water-project"]', 'Water project');
        $this->assertSelectorTextContains('nav[aria-label="Projectpagina\'s"]', 'Pagina 2 van 2');

        $client->click($crawler->selectLink('Water project')->link());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'For project water-project');
    }

    public function testNoResultsShowRecoveryLinkAndKeepSearchFilter(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findPublicPage')
            ->with('unknown', false, 1, 12)
            ->willReturn([
                'items' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
            ]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects?q=unknown&remote=no');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.projects-empty-state', 'Geen projecten gevonden');
        $this->assertSelectorExists('a[href="/projects"]');
        $this->assertSelectorExists('input[name="q"][value="unknown"]');
        $this->assertSelectorExists('option[value="no"][selected]');
    }

    public function testInvalidFilterAndPageAreReportedAndRecovered(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findPublicPage')
            ->with('', null, 1, 12)
            ->willReturn([
                'items' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
            ]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects?remote=invalid&page=0');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[role="alert"]', 'De gekozen projectfilter is ongeldig.');
        $this->assertSelectorTextContains('[role="alert"]', 'Het paginanummer is ongeldig.');
    }

    public function testAnonymousVisitorOnlySeesPublicProjectCardContent(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findPublicPage')
            ->with('', null, 1, 12)
            ->willReturn([
                'items' => [[
                    'title' => 'Public project',
                    'summary' => 'A public project summary.',
                    'slug' => 'public-project',
                    'remotePossible' => false,
                    'description' => 'PRIVATE FULL PROJECT DESCRIPTION',
                    'applicationMotivation' => 'PRIVATE APPLICATION MOTIVATION',
                    'participantEmail' => 'private@example.test',
                ]],
                'total' => 1,
                'page' => 1,
                'pages' => 1,
            ]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $crawler = $client->request('GET', '/projects');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2 a[href="/projects/public-project"]', 'Public project');
        $this->assertStringNotContainsString('PRIVATE FULL PROJECT DESCRIPTION', $crawler->html());
        $this->assertStringNotContainsString('PRIVATE APPLICATION MOTIVATION', $crawler->html());
        $this->assertStringNotContainsString('private@example.test', $crawler->html());
    }

    public function testOverlongSearchTermIsReportedAndRecovered(): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findPublicPage')
            ->with('', null, 1, 12)
            ->willReturn([
                'items' => [],
                'total' => 0,
                'page' => 1,
                'pages' => 1,
            ]);

        $client = static::createClient();
        static::getContainer()->set(ProjectRepository::class, $repository);
        $client->request('GET', '/projects?q=' . str_repeat('a', 101));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[role="alert"]', 'maximaal 100 tekens');
        $this->assertSelectorExists('input[name="q"][value=""]');
    }

    // details
    public function testShouldDiplayProjectTitle(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectDescription(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectStatus(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectStartdate(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectEndDate(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayProjectCapacity(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayIfRemoteIsPossible(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayOwner(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldDiplayOwnerOrginasation(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // apply

    public function testShouldBeAbleToSendApplyForm(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // chat

    public function testShouldDiplayMessages(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testShouldBeAbleToAddMessage(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // participants

    public function testShouldDiplayParticipants(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // tasks

    public function testShouldDiplayTasks(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // enrollTask

    public function testWhenNotLoggedInShouldBeRedirectedFromEnrollTask(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldBeAbleToEnroll(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // updates

    public function testWhenNotOwnerShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenOwnerShouldDisplayPage(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenOwnerAndSubmitUpdateShouldUpdateProject(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // workPackages

    public function testWhenWorkPackagesExistShouldDisplayThem(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenWorkPackagesDoesntExistShouldDisplayNoWorkPackagesAvailable(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
