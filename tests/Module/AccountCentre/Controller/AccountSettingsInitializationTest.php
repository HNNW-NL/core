<?php

namespace App\Tests\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
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
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $account = $entityManager->getRepository(Account::class)->findOneBy(['username' => 'TestUser']);
        self::assertInstanceOf(Account::class, $account);
        $accountId = $account->getId();
        $settings = $entityManager->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);
        if ($settings !== null) {
            $entityManager->remove($settings);
            $entityManager->flush();
        }
        $client->loginUser($account);

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
}
