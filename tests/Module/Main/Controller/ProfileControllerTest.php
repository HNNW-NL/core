<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
    // Index
    public function testWhenLoggedInShouldDisplayUserName(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#profile-text > p', "TestUser");
    }

    public function testWhenLoggedInShouldDisplayProfielFoto(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayLocation(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayStatus(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayLastLogin(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayExpertise(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplaySocials(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInShouldDisplayAchievements(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        // retrieve the test user
        $repo = $em->getRepository(Account::class);
        $testUser = $repo->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $client->loginUser($testUser);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();

        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenNotLoggedInShouldRedirectToLoginPage(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em  = $container->get(EntityManagerInterface::class);

        $crawler = $client->request('GET', '/profile');

        $this->assertResponseIsSuccessful();
        $this->assertResponseRedirects("auth.login");
    }
}
