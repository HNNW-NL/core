<?php

namespace App\Tests;

use App\DataFixtures\Account\AccountFixtures;
use App\DataFixtures\Project\PublicProjectListingFixtures;
use App\DataFixtures\Project\ProjectFixtures;
use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Test\FixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MainControllerTest extends WebTestCase
{
    use FixturesTrait;

    // Index

    public function testShouldDisplayHome(): void
    {
        $this->loadFixtures([
            ProjectFixtures::class,
        ]);

        $client = static::createClient();
        $crawler = $client->request('GET', '/');



        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Openbare projecten');
        $this->assertSelectorTextContains(
            '.hero-text',
            'HNNW is een open source samenwerkingsplatform voor ambtenaren en publieke teams.'
        );
        $this->assertSelectorTextContains('.hero-reassurance', 'Voor ambtenaren en publieke teams.');
        $this->assertSelectorExists('body > a.skip-link[href="#main-content"]');
        $this->assertSelectorExists('.open-project-card');
        $this->assertSelectorTextContains('.open-project-card h3', 'Gepubliceerd testproject');
        $this->assertSelectorExists('a[href="/projects"]');
        $this->assertSelectorExists('a[href="/auth/register"]');
        $this->assertStringNotContainsString(
            'Projectinformatie is op dit moment niet beschikbaar.',
            $crawler->text(),
        );
        $this->assertStringNotContainsString('Test Project', $crawler->text());
    }

    public function testShouldDisplayAccountLinkWhenLoggedIn(): void
    {
        $this->loadFixtures([
            AccountFixtures::class,
        ]);

        $client = static::createClient();
        $account = static::getContainer()
            ->get(EntityManagerInterface::class)
            ->getRepository(Account::class)
            ->findOneBy(['email' => 'Test@test.nl']);

        self::assertInstanceOf(Account::class, $account);
        $client->loginUser($account);
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/account"]');
        $this->assertSelectorTextContains('.hero-actions', 'Mijn account');
        $this->assertSelectorNotExists('a[href="/auth/register"]');
    }

    public function testShouldDisplayHomeWithoutProjects(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains(
            'h2',
            'Openbare projecten'
        );

        $this->assertSelectorExists('.open-projects-empty');
        $this->assertSelectorExists('a[href="/projects"]');
        $this->assertSelectorExists('a[href="/auth/register"]');

        $this->assertSelectorTextContains(
            '.open-projects-empty',
            'Er zijn op dit moment geen openbare projecten beschikbaar.'
        );

        $this->assertSelectorNotExists('.open-project-card');
    }

    public function testHomeUsesTheSamePublicProjectSelectionAsTheProjectsPage(): void
    {
        $this->loadFixtures([
            PublicProjectListingFixtures::class,
        ]);

        $client = static::createClient();
        $home = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertCount(6, $home->filter('.open-project-card'));
        $this->assertStringNotContainsString('MS04 conceptproject', $home->text());
        $this->assertSelectorTextContains('.project-status', 'Gepubliceerd');
        $this->assertSelectorTextContains('.project-location', 'Op afstand mogelijk');
        $this->assertSelectorTextContains('.project-application-open', 'Aanmelden mogelijk');
    }

    public function testProjectsPageUsesThePublicProjectSelection(): void
    {
        $this->loadFixtures([
            ProjectFixtures::class,
        ]);

        $client = static::createClient();
        $crawler = $client->request('GET', '/projects');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Openbare projecten');
        $this->assertSelectorTextContains('.open-project-card h2', 'Gepubliceerd testproject');
        $this->assertSelectorTextContains('.project-status', 'Gepubliceerd');
        $this->assertSelectorTextContains('.project-location', 'Op afstand mogelijk');
        $this->assertStringNotContainsString('Test Project', $crawler->text());
    }

    public function testProjectsPagePaginatesPublicResultsAndExcludesDrafts(): void
    {
        $this->loadFixtures([
            PublicProjectListingFixtures::class,
        ]);

        $client = static::createClient();
        $firstPage = $client->request('GET', '/projects?q=MS04');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.project-results-count', '21 projecten gevonden');
        $this->assertCount(20, $firstPage->filter('.open-project-card'));
        $this->assertStringNotContainsString('MS04 conceptproject', $firstPage->text());
        $this->assertSelectorExists('a[href*="page=2"]');

        $firstPageSlugs = $firstPage
            ->filter('.open-project-card h2 a')
            ->each(static fn ($link) => $link->attr('href'));

        $secondPage = $client->request('GET', '/projects?q=MS04&page=2');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $secondPage->filter('.open-project-card'));
        $this->assertSelectorTextContains('.project-results-count', '21 projecten gevonden');

        $secondPageSlugs = $secondPage
            ->filter('.open-project-card h2 a')
            ->each(static fn ($link) => $link->attr('href'));
        $this->assertSame([], array_intersect($firstPageSlugs, $secondPageSlugs));

        $openProjects = $client->request('GET', '/projects?q=MS04&open=1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.project-results-count', '20 projecten gevonden');
        $this->assertCount(20, $openProjects->filter('.open-project-card'));

        $summarySearch = $client->request('GET', '/projects?q=samenvatting');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.project-results-count', '21 projecten gevonden');
        $this->assertStringNotContainsString('MS04 conceptproject', $summarySearch->text());
    }

    //About

    public function testShouldDisplayAbout(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/about');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'About');
    }

    // viewProfile

    public function testWhenUserDoesntExistShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayUserName(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayProfielFoto(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayLocation(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayStatus(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserExistShouldDisplayLastLogin(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testUserExistShouldDisplayExpertise(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testUserExistShouldDisplaySocials(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testUserExistInShouldDisplayAchievements(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
}
