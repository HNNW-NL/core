<?php

namespace App\Tests;

use App\Repository\Project\ProjectRepository;
use App\DataFixtures\Account\AccountFixtures;
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
