<?php

namespace App\Tests\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Common\Status;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountSettingsInitializationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testFirstValidPostCreatesSettingsAfterReadOnlyGet(): void
    {
        $client = static::createClient();
        $account = $this->loginWithNewAccount($client);
        $accountId = $account->getId();

        $page = $client->request('GET', '/account/settings');
        self::assertResponseIsSuccessful();
        self::assertSame('nl', $page->filter('#language')->attr('value'));
        self::assertCount(1, $page->filter('#email_notifications_enabled[checked]'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]));

        $client->submitForm('Wijzigingen opslaan', ['language' => 'zz']);
        self::assertResponseRedirects('/account/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Kies een geldige taal');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]));

        $client->submitForm('Wijzigingen opslaan', ['language' => 'en', 'theme' => 'light']);
        self::assertResponseRedirects('/account/settings');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame('en', $client->getCrawler()->filter('#language')->attr('value'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $saved = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]);
        self::assertInstanceOf(AccountSetting::class, $saved);
        self::assertSame('en', $saved->getLanguage());
        self::assertSame('light', $saved->getTheme());
        self::assertTrue($saved->isEmailNotificationsEnabled());

        $client->submitForm('Wijzigingen opslaan', ['language' => 'nl', 'email_notifications_enabled' => false]);
        self::assertResponseRedirects('/account/settings');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(0, $client->getCrawler()->filter('#email_notifications_enabled[checked]'));
        $settingsRepository = static::getContainer()->get(EntityManagerInterface::class)->getRepository(AccountSetting::class);
        $savedAgain = $settingsRepository->findOneBy(['account' => $accountId]);
        self::assertEquals($saved->getId(), $savedAgain->getId());
        self::assertFalse($savedAgain->isEmailNotificationsEnabled());
    }

    public function testNotificationsCanBeSavedBeforeAccountSettingsExist(): void
    {
        $client = static::createClient();
        $accountId = $this->loginWithNewAccount($client)->getId();

        $client->request('GET', '/account/notifications');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#notify_projects[checked]');
        self::assertSelectorExists('#notify_newsletter[checked]');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]));

        $client->request('POST', '/account/notifications', ['notify_projects' => 'on']);
        self::assertResponseStatusCodeSame(403);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]));

        $client->request('GET', '/account/notifications');
        $client->submitForm('Voorkeuren Opslaan', ['notify_projects' => false, 'notify_newsletter' => false]);
        self::assertResponseRedirects('/account/notifications');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#notify_projects[checked], #notify_newsletter[checked]');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $saved = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]);
        self::assertInstanceOf(AccountSetting::class, $saved);
        self::assertFalse($saved->isEmailNotificationsEnabled());

        $client->request('GET', '/account/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#email_notifications_enabled[checked]');
        $client->submitForm('Wijzigingen opslaan', ['theme' => 'light']);
        self::assertResponseRedirects('/account/settings');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $savedAgain = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $accountId]);
        self::assertEquals($saved->getId(), $savedAgain->getId());
        self::assertSame('light', $savedAgain->getTheme());
        self::assertFalse($savedAgain->isEmailNotificationsEnabled());
    }

    private function loginWithNewAccount(KernelBrowser $client): Account
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $status = $entityManager->getRepository(Status::class)->findOneBy(['scope' => 'account', 'name' => 'active']);
        self::assertInstanceOf(Status::class, $status);

        // The configured DAMA extension rolls back the test account after each test.
        $account = new Account();
        $username = 'settings-test-' . $account->getId();
        $account->setUsername($username)
            ->setEmail($username . '@example.test')
            ->setPasswordHash(password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT))
            ->setStatus($status);
        $entityManager->persist($account);
        $entityManager->flush();
        $client->loginUser($account);

        return $account;
    }
}
