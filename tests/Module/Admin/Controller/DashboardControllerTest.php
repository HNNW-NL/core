<?php

namespace App\Tests\Module\Admin\Controller;

use App\Repository\Org\AccountRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private ?AccountRepository $accountRepository;
    private ?KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->accountRepository = static::getContainer()
            ->get(AccountRepository::class);
    }

    // index

    public function testWhenUserIsNotAdminShouldRedirectFromIndex(): void
    {     
        // retrieve the test user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenUserIsAdminShouldDisplayIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function testWhenUserIsAdminShouldDisplayLogsInIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains(".dashboard div:nth-of-type(1)> ul > li","A system message");
    }

    public function testWhenUserIsAdminShouldDisplayAccountsInIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $crawler->filter(".card")->first()->nextAll()->filter("ul >li");

        $this->assertSelectorTextContains(".dashboard div:nth-of-type(2)  > ul > li:nth-of-type(1)","TestUser");

        $this->assertSelectorTextContains(".dashboard div:nth-of-type(2) > ul > li:nth-of-type(2)","AdminUser");
    }

    public function testWhenUserIsAdminShouldDisplayProfilesInIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $crawler->filter(".card")->first()->nextAll()->filter("ul >li");

        $this->assertSelectorTextContains(".dashboard div:nth-of-type(3)  > ul > li:nth-of-type(1)","Test User");

        $this->assertSelectorTextContains(".dashboard div:nth-of-type(3) > ul > li:nth-of-type(2)","Admin User");
    }

    

    // logs

    public function testWhenUserIsNotAdminShouldRedirectFromLogs(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldDisplayLogs(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // notifications

    public function testWhenUserIsNotAdminShouldRedirectFromNotifications(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldDisplayNotifications(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // settings

    public function testWhenUserIsNotAdminShouldRedirectFromSettings(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldDisplaySettings(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // socialManageStaff

    public function testWhenUserIsNotAdminShouldRedirectFromSocialManageStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldDisplaySocialManageStaff(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // statuses

    public function testWhenUserIsNotAdminShouldRedirectFromStatuses(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenUserIsAdminShouldDisplaySocialManageStatuses(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->accountRepository = null;
        $this->client = null;
    }
}
