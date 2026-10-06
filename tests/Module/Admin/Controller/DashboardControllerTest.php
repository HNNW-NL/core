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

    public function testWhenUserIsAdminShouldDisplayActiveAccountsInIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains(".dashboard div:nth-of-type(1)> .content ","1");
    }

    public function testWhenUserIsAdminShouldDisplayActiveOrganizationsInIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();

        $this->assertAnySelectorTextContains(".dashboard div:nth-of-type(2)> .content ","1");
    }

    public function testWhenUserIsAdminShouldDisplayActiveProjectsInIndex(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $crawler->filter(".card")->first()->nextAll()->filter("ul >li");

        $this->assertAnySelectorTextContains(".dashboard div:nth-of-type(3)> .content ","1");
    }

    

    // logs

    public function testWhenUserIsNotAdminShouldRedirectFromLogs(): void
    {     
        // retrieve the test user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Test@test.nl']);

        // simulate $testUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin/logs');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenUserIsAdminShouldDisplayLogs(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin/logs');

        $this->assertResponseIsSuccessful();
    }

    public function testWhenUserIsAdminShouldDisplayAuditLogsInLogs(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin/logs');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains("#auditLogsTable > tr:nth-of-type(1) > td:nth-of-type(2)","TestUser");
        $this->assertSelectorTextContains("#auditLogsTable > tr:nth-of-type(1) > td:nth-of-type(3)","Test@test.nl");
        $this->assertSelectorTextContains("#auditLogsTable > tr:nth-of-type(1) > td:nth-of-type(4)","login");
        $this->assertSelectorTextContains("#auditLogsTable > tr:nth-of-type(1) > td:nth-of-type(5)","none");
        $this->assertSelectorTextContains("#auditLogsTable > tr:nth-of-type(1) > td:nth-of-type(6)","none");
        $this->assertSelectorTextContains("#auditLogsTable > tr:nth-of-type(1) > td:nth-of-type(7)","127.0.0.0");
    }

    public function testWhenUserIsAdminShouldDisplaySystemLogsInLogs(): void
    {
        // retrieve the admin user
        $testUser = $this->accountRepository->findOneBy(['email' => 'Admin@test.nl']);

        // simulate $adminUser being logged in
        $this->client->loginUser($testUser);

        $crawler = $this->client->request('GET', '/admin/logs');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains("#systemLogsTable > tr:nth-of-type(1) > td:nth-of-type(2)","1");
        $this->assertSelectorTextContains("#systemLogsTable > tr:nth-of-type(1) > td:nth-of-type(3)","1");
        $this->assertSelectorTextContains("#systemLogsTable > tr:nth-of-type(1) > td:nth-of-type(4)","A system message");
        $this->assertSelectorTextContains("#systemLogsTable > tr:nth-of-type(1) > td:nth-of-type(5)","/");
        $this->assertSelectorTextContains("#systemLogsTable > tr:nth-of-type(1) > td:nth-of-type(6)","get");
        $this->assertSelectorTextContains("#systemLogsTable > tr:nth-of-type(1) > td:nth-of-type(7)","127.0.0.1");
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
