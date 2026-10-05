<?php

namespace App\Tests\Module\AccountCentre\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Availability;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class AcountsControllerTest extends WebTestCase
{
    // symfony's stateless csrf (config/packages/csrf.yaml) keurt een post goed als de Origin-header bij de site hoort, een browser stuurt die zelf mee maar de testclient niet
    private const ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    // index

    public function testWhenNotLoggedInShouldRedirectFromIndex(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplayIndex(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welkom bij je Account');
    }

    // applications

    public function testWhenNotLoggedInShouldRedirectFromApplications(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/applications');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInAndHaveApplicationsShouldDisplayApplications(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account/applications');

        $this->assertResponseIsSuccessful();
        // de fixture heeft een aanmelding voor het project "Test Project"
        $this->assertSelectorTextContains('.account-card h3', 'Test Project');
    }
    public function testWhenLoggedInAndHaveNoApplicationsShouldDisplayNoApplications(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Admin@test.nl');

        $client->request('GET', '/account/applications');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.account-card-empty h3', 'Geen aanmeldingen');
    }

    //applicationsCancel

    public function testWhenLoggedInAndHaveApplicationsShouldRemoveApplication(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndDoesntHaveApplicationsShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndNotOwnerofApplicationShouldNotRemoveApplication(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    //availability

    public function testWhenNotLoggedInShouldRedirectFromAvailability(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/availability');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplayAvailabilityListWithoutSaveForm(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/availability');

        $this->assertResponseIsSuccessful();
        // de fixture heeft zeven rijen (maandag tot en met zondag) en die komen nu uit de entity
        $this->assertCount(7, $crawler->filter('table tbody tr'));
        $this->assertSelectorTextContains('table tbody', 'Monday');
        // het opslaan-formulier is weggehaald, dat komt uit pr 3 van team 4; deze controle mag weg zodra die pr het opslaan terugbrengt
        $this->assertSelectorNotExists('form[action="/account/availability"]');
    }

    public function testWhenPostingToAvailabilityShouldNotBeAllowed(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        // de route staat alleen nog GET toe; deze test mag weg zodra pr 3 van team 4 het opslaan terugbrengt
        $client->request('POST', '/account/availability', [], [], self::ORIGIN);

        $this->assertResponseStatusCodeSame(405);
    }

    public function testWhenLoggedInSubmitFormWithTimeMondayChangedShouldSaveMondayTime(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInSubmitFormWithTimeWednesdayChangedShouldSaveWednesdayTime(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInSubmitFormWithTimeFridayChangedShouldSaveFridayTime(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInSubmitFormWithTimeSaturdayEnabledShouldSaveSaturday(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInSubmitFormWithTimeMondayDisabledShouldNotSaveMonday(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    //deleteAvailability

    public function testWhenLoggedInAndHaveAvailabilityShouldRemoveAvailability(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $availability = $this->findAvailability('Test@test.nl', 'Monday');
        $this->assertNull($availability->getDeletedAt());

        // het verwijder-formulier van deze rij staat op de pagina, met het echte csrf-token
        $crawler = $client->request('GET', '/account/availability');
        $form = $crawler->filter('form[action="/account/availability/' . $availability->getId() . '/delete"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/account/availability');

        // de rij blijft bestaan, alleen deleted_at is nu gevuld (soft delete)
        $this->assertNotNull($this->findAvailability('Test@test.nl', 'Monday')->getDeletedAt());
    }

    public function testWhenLoggedInAndDoesntHaveAvailabilityShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('POST', '/account/availability/' . Uuid::v4() . '/delete', ['_token' => 'maakt-niet-uit']);

        $this->assertResponseRedirects('/account/availability');

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-error', 'Beschikbaarheid niet gevonden.');
    }

    public function testWhenLoggedInAndNotOwnerofAvailabilityShouldNotRemoveAvailability(): void
    {
        $client = static::createClient();
        // de admin probeert een rij van de testgebruiker weg te halen
        $this->loginAs($client, 'Admin@test.nl');

        $availability = $this->findAvailability('Test@test.nl', 'Tuesday');

        $client->request('POST', '/account/availability/' . $availability->getId() . '/delete', ['_token' => 'maakt-niet-uit']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findAvailability('Test@test.nl', 'Tuesday')->getDeletedAt());
    }

    //experience

    public function testWhenNotLoggedInShouldRedirectFromExperience(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/experience');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplayExperienceForm(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account/experience');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/account/experience"] input[name="experience[jobTitle]"]');
        // de fixture-ervaring staat in de lijst
        $this->assertSelectorTextContains('.account-card h3', 'A Job Title');
    }

    public function testWhenLoggedInShouldSaveExperience(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/experience');
        $form = $crawler->selectButton('Ervaring Opslaan')->form();
        $form['experience[jobTitle]'] = 'Testfunctie';
        $form['experience[organisationName]'] = 'Testbedrijf';
        $form['experience[startDate]'] = '2024-01-01';
        $form['experience[endDate]'] = '2024-12-31';
        $form['experience[description]'] = 'Een korte beschrijving';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseRedirects('/account/experience');

        $experience = $this->findExperience('Testfunctie');
        $this->assertNotNull($experience);
        $this->assertSame('Testbedrijf', $experience->getOrganisationName());
        $this->assertSame('2024-01-01', $experience->getStartDate()->format('Y-m-d'));
        $this->assertSame('2024-12-31', $experience->getEndDate()->format('Y-m-d'));
        $this->assertSame('Een korte beschrijving', $experience->getDescription());
        $this->assertFalse($experience->isCurrent());
        // de ervaring hangt aan het profiel van de ingelogde gebruiker
        $this->assertSame('Test@test.nl', $experience->getProfile()->getAccount()->getEmail());
    }

    public function testWhenLoggedInAndStartTimeIsAfterEndTimeShouldGiveError(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    public function testWhenLoggedInAndJobTitleIsEmptyTimeShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/experience');
        $form = $crawler->selectButton('Ervaring Opslaan')->form();
        $form['experience[jobTitle]'] = '';
        $form['experience[organisationName]'] = 'Testbedrijf';
        $form['experience[startDate]'] = '2024-01-01';
        $client->submit($form, [], self::ORIGIN);

        // bij een fout wordt het formulier opnieuw getoond (200) in plaats van een redirect
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form[action="/account/experience"]', 'Vul een functietitel in.');
    }

    public function testWhenLoggedInAndOrginazationNameIsEmptyTimeShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/experience');
        $form = $crawler->selectButton('Ervaring Opslaan')->form();
        $form['experience[jobTitle]'] = 'Testfunctie';
        $form['experience[organisationName]'] = '';
        $form['experience[startDate]'] = '2024-01-01';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form[action="/account/experience"]', 'Vul een bedrijf of organisatie in.');
    }

    public function testWhenLoggedInAndStartDateIsEmptyTimeShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/experience');
        $form = $crawler->selectButton('Ervaring Opslaan')->form();
        $form['experience[jobTitle]'] = 'Testfunctie';
        $form['experience[organisationName]'] = 'Testbedrijf';
        $form['experience[startDate]'] = '';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form[action="/account/experience"]', 'Vul een startdatum in.');
    }

    // deleteExperience

    public function testWhenLoggedInAndHaveExperienceShouldRemoveExperience(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $experience = $this->findExperience('A Job Title');
        $this->assertNull($experience->getDeletedAt());

        $crawler = $client->request('GET', '/account/experience');
        $form = $crawler->filter('form[action="/account/experience/' . $experience->getId() . '/delete"]')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/account/experience');

        // soft delete: de rij blijft staan met een gevulde deleted_at
        $this->assertNotNull($this->findExperience('A Job Title')->getDeletedAt());
    }

    public function testWhenLoggedInAndDoesntHaveExperienceShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('POST', '/account/experience/' . Uuid::v4() . '/delete', ['_token' => 'maakt-niet-uit']);

        $this->assertResponseRedirects('/account/experience');

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-error', 'Ervaring niet gevonden.');
    }

    public function testWhenLoggedInAndNotOwnerofExperienceShouldNotRemoveExperience(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Admin@test.nl');

        $experience = $this->findExperience('A Job Title');

        $client->request('POST', '/account/experience/' . $experience->getId() . '/delete', ['_token' => 'maakt-niet-uit']);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNull($this->findExperience('A Job Title')->getDeletedAt());
    }

    // modify

    public function testWhenNotLoggedInShouldRedirectFromModify(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/modify');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplayModifyFormWithEmailAndDisplayName(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account/modify');

        $this->assertResponseIsSuccessful();
        // e-mail en weergavenaam waren uit het formulier verdwenen, ze staan er nu weer in
        $this->assertInputValueSame('modify_account[email]', 'Test@test.nl');
        $this->assertSelectorExists('input[name="modify_account[displayName]"]');
    }

    public function testWhenLoggedInSubmitFormShouldSaveProfile(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/modify');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        $form['modify_account[email]'] = 'nieuw@test.nl';
        $form['modify_account[firstName]'] = 'Nieuwe';
        $form['modify_account[lastName]'] = 'Naam';
        $form['modify_account[displayName]'] = 'Nieuwe Weergavenaam';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseRedirects('/account/modify');

        $account = $this->findAccountByUsername('TestUser');
        $this->assertSame('nieuw@test.nl', $account->getEmail());

        $profile = $this->findProfile($account);
        $this->assertSame('Nieuwe', $profile->getFirstName());
        $this->assertSame('Naam', $profile->getLastName());
        $this->assertSame('Nieuwe Weergavenaam', $profile->getDisplayName());

        // het e-mailadres is ook de login-naam, na het wijzigen moet je ingelogd blijven
        $client->request('GET', '/account/modify');
        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('modify_account[email]', 'nieuw@test.nl');
    }

    public function testWhenLoggedInSubmitFormShouldSaveSettings(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/modify');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        $form['modify_account[language]'] = 'en-US';
        $form['modify_account[theme]'] = 'light';
        $form['modify_account[profileVisibility]'] = 'public';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseRedirects('/account/modify');

        $settings = $this->findSettings($this->findAccountByUsername('TestUser'));
        $this->assertSame('en-US', $settings->getLanguage());
        $this->assertSame('light', $settings->getTheme());
        $this->assertSame('public', $settings->getProfileVisibility());
    }

    public function testWhenLoggedInSubmitFormWithOwnEmailShouldSave(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        // je eigen e-mailadres opnieuw opsturen is geen dubbel adres, dat moet gewoon lukken
        $crawler = $client->request('GET', '/account/modify');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        $form['modify_account[email]'] = 'Test@test.nl';
        $form['modify_account[displayName]'] = 'Tester';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseRedirects('/account/modify');

        $account = $this->findAccountByUsername('TestUser');
        $this->assertSame('Tester', $this->findProfile($account)->getDisplayName());
    }

    public function testWhenLoggedInSubmitFormWithInvalidEmailShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $crawler = $client->request('GET', '/account/modify');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        $form['modify_account[email]'] = 'geen-email';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form[action="/account/modify"]', 'Dit is geen geldig e-mailadres.');

        // er is niets opgeslagen
        $this->assertSame('Test@test.nl', $this->findAccountByUsername('TestUser')->getEmail());
    }

    public function testWhenLoggedInSubmitFormWithEmailOfOtherAccountShouldGiveError(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        // het adres van de admin is al in gebruik, de kolom is uniek dus dit mag niet door naar de database
        $crawler = $client->request('GET', '/account/modify');
        $form = $crawler->selectButton('Wijzigingen opslaan')->form();
        $form['modify_account[email]'] = 'Admin@test.nl';
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form[action="/account/modify"]', 'Dit e-mailadres is al in gebruik.');

        $this->assertSame('Test@test.nl', $this->findAccountByUsername('TestUser')->getEmail());
        $this->assertSame('Admin@test.nl', $this->findAccountByUsername('AdminUser')->getEmail());

        // en je bent nog steeds ingelogd
        $client->request('GET', '/account/modify');
        $this->assertResponseIsSuccessful();
    }

    // notifications

    public function testWhenNotLoggedInShouldRedirectFromNotifications(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/notifications');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplayNotificationsForm(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account/notifications');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[action="/account/notifications"] input[name="notification_settings[emailNotificationsEnabled]"]');
    }

    public function testWhenLoggedInSubmitFormAndnotifyProjectsEnabledShouldEnableNotifyProjects(): void
    {
        $client = static::createClient();
        $account = $this->loginAs($client, 'Test@test.nl');

        // de fixture staat al aan, dus eerst uitzetten zodat we echt een verandering zien
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->findSettings($account)->setEmailNotificationsEnabled(false);
        $em->flush();

        $crawler = $client->request('GET', '/account/notifications');
        $form = $crawler->selectButton('Voorkeuren Opslaan')->form();
        $form['notification_settings[emailNotificationsEnabled]']->tick();
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseRedirects('/account/notifications');
        $this->assertTrue($this->findSettings($this->findAccountByUsername('TestUser'))->isEmailNotificationsEnabled());
    }

    public function testWhenLoggedInSubmitFormAndnotifyProjectsDisabledShouldDisableNotifyProjects(): void
    {
        $client = static::createClient();
        $account = $this->loginAs($client, 'Test@test.nl');
        $this->assertTrue($this->findSettings($account)->isEmailNotificationsEnabled());

        $crawler = $client->request('GET', '/account/notifications');
        $form = $crawler->selectButton('Voorkeuren Opslaan')->form();
        // een vinkje dat uit staat stuurt een browser helemaal niet mee
        $form['notification_settings[emailNotificationsEnabled]']->untick();
        $client->submit($form, [], self::ORIGIN);

        $this->assertResponseRedirects('/account/notifications');
        $this->assertFalse($this->findSettings($this->findAccountByUsername('TestUser'))->isEmailNotificationsEnabled());
    }

    public function testWhenLoggedInShouldNotShowNewsletterCheckbox(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account/notifications');

        $this->assertResponseIsSuccessful();
        // het nieuwsbrief vinkje is weggehaald, het schreef naar dezelfde kolom als het andere vinkje
        $this->assertSelectorNotExists('input[name*="newsletter"]');
        $this->assertSelectorNotExists('input[name*="Newsletter"]');
    }

    public function testWhenLoggedInSubmitFormWithOldNewsletterFieldShouldNotSave(): void
    {
        $client = static::createClient();
        $account = $this->loginAs($client, 'Test@test.nl');
        $this->assertTrue($this->findSettings($account)->isEmailNotificationsEnabled());

        // het echte token meesturen, zo weet je zeker dat het de post is die faalt op het onbekende veld en niet op het token
        $crawler = $client->request('GET', '/account/notifications');
        $token = $crawler->filter('input[name="notification_settings[_token]"]')->attr('value');

        // het oude veld toch meesturen: het formulier kent het niet meer en keurt de hele post af
        $client->request('POST', '/account/notifications', [
            'notification_settings' => [
                '_token' => $token,
                'newsletterEnabled' => '1',
            ],
        ], [], self::ORIGIN);

        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->findSettings($this->findAccountByUsername('TestUser'))->isEmailNotificationsEnabled());
    }

    // projects

    public function testWhenNotLoggedInShouldRedirectFromProjects(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/projects');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInAndHaveProjectsShouldDisplayProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }
    public function testWhenLoggedInAndHaveNoProjectsShouldDisplayNoProjects(): void
    {
        //Reminder that test is not yet implemented
        $this->assertTrue(false);
    }

    // reputation

    public function testWhenNotLoggedInShouldRedirectFromReputation(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/reputation');

        $this->assertResponseRedirects("/auth/login");
    }

    // reviews

    public function testWhenNotLoggedInShouldRedirectFromReviews(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/reviews');

        $this->assertResponseRedirects("/auth/login");
    }

    // settings

    public function testWhenNotLoggedInShouldRedirectFromSettings(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/settings');

        $this->assertResponseRedirects("/auth/login");
    }

    // signoff

    public function testWhenNotLoggedInShouldRedirectFromSignoff(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/signoff');

        $this->assertResponseRedirects("/auth/login");
    }

    public function testWhenLoggedInShouldDisplaySignoffWithLogoutLink(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'Test@test.nl');

        $client->request('GET', '/account/signoff');

        $this->assertResponseIsSuccessful();
        // de uitloglink wijst naar de route _logout_main die symfony zelf aanmaakt
        $this->assertSelectorExists('a[href="/auth/logout"]');
    }

    // skills

    public function testWhenNotLoggedInShouldRedirectFromSkills(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/account/skills');

        $this->assertResponseRedirects("/auth/login");
    }

    // hulpfuncties

    // de entity manager altijd via de container van dit moment pakken, de kernel start tussen twee requests opnieuw
    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    // logt een fixture-account in zonder het login-formulier, zo testen we alleen het account centre
    private function loginAs(KernelBrowser $client, string $email): Account
    {
        $account = $this->entityManager()->getRepository(Account::class)->findOneBy(['email' => $email]);
        $client->loginUser($account);

        return $account;
    }

    private function findAccountByUsername(string $username): Account
    {
        return $this->entityManager()->getRepository(Account::class)->findOneBy(['username' => $username]);
    }

    private function findProfile(Account $account): Profile
    {
        return $this->entityManager()->getRepository(Profile::class)->findOneBy(['account' => $account]);
    }

    private function findSettings(Account $account): AccountSetting
    {
        return $this->entityManager()->getRepository(AccountSetting::class)->findOneBy(['account' => $account]);
    }

    private function findAvailability(string $email, string $dayOfWeek): Availability
    {
        $account = $this->entityManager()->getRepository(Account::class)->findOneBy(['email' => $email]);
        $profile = $this->findProfile($account);

        return $this->entityManager()->getRepository(Availability::class)->findOneBy(['profile' => $profile, 'dayOfWeek' => $dayOfWeek]);
    }

    private function findExperience(string $jobTitle): ?ProfileExperience
    {
        return $this->entityManager()->getRepository(ProfileExperience::class)->findOneBy(['jobTitle' => $jobTitle]);
    }
}
