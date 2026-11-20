<?php

namespace App\Tests\Module\AccountCentre\Controller;

use App\DataFixtures\Account\AccountFixtures;
use App\DataFixtures\Account\ProfileFixtures;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ModifyAccountControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    private Account $account;

    private Profile $profile;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $this->em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $this->account = $this->em
            ->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        self::assertNotNull($this->account);

        $this->profile = $this->em
            ->getRepository(Profile::class)
            ->findOneBy(['account' => $this->account]);

        self::assertNotNull($this->profile);

        $this->client->loginUser($this->account);
    }

    /**
     * Test of de wijzigpagina correct wordt weergegeven.
     */
    public function testModifyPageLoadsSuccessfully(): void
    {
        $client = $this->client;

        $crawler = $client->request('GET', '/modify');

        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Profiel Wijzigen');

        self::assertSelectorExists('input[name="username"]');
        self::assertSelectorExists('input[name="first_name"]');
        self::assertSelectorExists('input[name="last_name"]');
        self::assertSelectorExists('input[name="display_name"]');
        self::assertSelectorExists('input[name="avatar_url"]');
        self::assertSelectorExists('input[name="location"]');
        self::assertSelectorExists('textarea[name="bio"]');

        self::assertSame(
            'TestUser',
            $crawler->filter('#username')->attr('value')
        );
    }

    /**
     * Test of profielgegevens succesvol gewijzigd kunnen worden.
     */
    public function testModifyAccountSuccessfully(): void
    {
        $client = $this->client;

        $crawler = $client->request('GET', '/modify');

        $form = $crawler->selectButton('Wijzigingen opslaan')->form();

        $form['username'] = 'NewTest';
        $form['first_name'] = 'Nieuwe';
        $form['last_name'] = 'Gebruiker';
        $form['display_name'] = 'Nieuwe Gebruiker';
        $form['avatar_url'] = 'https://example.com/avatar.png';
        $form['location'] = 'Amsterdam';
        $form['bio'] = 'Dit is mijn nieuwe bio.';

        $client->submit($form);

        self::assertResponseRedirects();

        $client->followRedirect();

        self::assertSelectorTextContains(
            '.account-flash-success',
            'Je wijzigingen zijn succesvol opgeslagen!'
        );

        // Database opnieuw ophalen
        $this->em->clear();

        $updatedAccount = $this->em
            ->getRepository(Account::class)
            ->findOneBy(['username' => 'NewTest']);

        $updatedProfile = $this->em
            ->getRepository(Profile::class)
            ->findOneBy(['account' => $updatedAccount]);

        self::assertNotNull($updatedAccount);
        self::assertNotNull($updatedProfile);

        self::assertSame('Nieuwe', $updatedProfile->getFirstName());
        self::assertSame('Gebruiker', $updatedProfile->getLastName());
        self::assertSame('Nieuwe Gebruiker', $updatedProfile->getDisplayName());
        self::assertSame('https://example.com/avatar.png', $updatedProfile->getAvatarUrl());
        self::assertSame('Amsterdam', $updatedProfile->getLocation());
        self::assertSame('Dit is mijn nieuwe bio.', $updatedProfile->getDescription());
    }

    /**
     * Test of de username gewijzigd kan worden.
     */
    public function testUsernameCanBeChanged(): void
    {
        $client = $this->client;

        $crawler = $client->request('GET', '/modify');

        $form = $crawler->selectButton('Wijzigingen opslaan')->form();

        $form['username'] = 'ChangedUser';

        $client->submit($form);

        self::assertResponseRedirects();

        $this->em->clear();

        $account = $this->em
            ->getRepository(Account::class)
            ->findOneBy(['username' => 'ChangedUser']);

        self::assertNotNull($account);
    }

    /**
     * Test dat een bestaande username niet gebruikt kan worden.
     */
    public function testDuplicateUsernameIsRejected(): void
    {
        $client = $this->client;

        $crawler = $client->request('GET', '/modify');

        $form = $crawler->selectButton('Wijzigingen opslaan')->form();

        $form['username'] = 'AdminUser';

        $client->submit($form);

        self::assertResponseRedirects();

        $client->followRedirect();

        self::assertSelectorTextContains(
            '.account-flash',
            'Username is al in gebruik.'
        );

        $this->em->clear();

        $account = $this->em
            ->getRepository(Account::class)
            ->findOneBy(['username' => 'TestUser']);

        self::assertNotNull($account);
    }

    /**
     * Test dat een lege username wordt geweigerd.
     */
    public function testEmptyUsernameIsRejected(): void
    {
        $this->assertInvalidFormField(
            'username',
            '',
            'Username invoer ongeldig.'
        );
    }

    /**
     * Test dat een username langer dan 12 tekens wordt geweigerd.
     */
    public function testUsernameLongerThan12CharactersIsRejected(): void
    {
        $this->assertInvalidFormField(
            'username',
            'UsernameTooLong',
            'Username invoer ongeldig.'
        );
    }

    /**
     * Test dat een lege voornaam wordt geweigerd.
     */
    public function testEmptyFirstNameIsRejected(): void
    {
        $this->assertInvalidFormField(
            'first_name',
            '',
            'Voornaam invoer ongeldig.'
        );
    }

    /**
     * Test dat een voornaam langer dan 50 tekens wordt geweigerd.
     */
    public function testFirstNameLongerThan50CharactersIsRejected(): void
    {
        $this->assertInvalidFormField(
            'first_name',
            str_repeat('A', 51),
            'Voornaam invoer ongeldig.'
        );
    }

    /**
     * Test dat een lege achternaam wordt geweigerd.
     */
    public function testEmptyLastNameIsRejected(): void
    {
        $this->assertInvalidFormField(
            'last_name',
            '',
            'Achternaam invoer ongeldig.'
        );
    }

    /**
     * Test dat een achternaam langer dan 50 tekens wordt geweigerd.
     */
    public function testLastNameLongerThan50CharactersIsRejected(): void
    {
        $this->assertInvalidFormField(
            'last_name',
            str_repeat('B', 51),
            'Achternaam invoer ongeldig.'
        );
    }

    /**
     * Test dat een display name langer dan 50 tekens wordt geweigerd.
     */
    public function testDisplayNameLongerThan50CharactersIsRejected(): void
    {
        $this->assertInvalidFormField(
            'display_name',
            str_repeat('C', 51),
            'Display name invoer ongeldig.'
        );
    }

    /**
     * Test dat een bio langer dan 250 tekens wordt geweigerd.
     */
    public function testBioLongerThan250CharactersIsRejected(): void
    {
        $this->assertInvalidFormField(
            'bio',
            str_repeat('D', 251),
            'Bio invoer ongeldig.'
        );
    }

    /**
     * Test dat een optionele display name leeg mag zijn.
     */
    public function testEmptyDisplayNameIsAllowed(): void
    {
        $this->assertValidOptionalField('display_name', '');
    }

    /**
     * Test dat een optionele bio leeg mag zijn.
     */
    public function testEmptyBioIsAllowed(): void
    {
        $this->assertValidOptionalField('bio', '');
    }

    /**
     * Herbruikbare methode voor ongeldige invoer.
     */
    private function assertInvalidFormField(
        string $field,
        string $value,
        string $expectedMessage
    ): void {
        $client = $this->client;

        $crawler = $client->request('GET', '/modify');

        $form = $crawler->selectButton('Wijzigingen opslaan')->form();

        $form[$field] = $value;

        $client->submit($form);

        self::assertResponseRedirects();

        $client->followRedirect();

        self::assertSelectorTextContains(
            '.account-flash',
            $expectedMessage
        );
    }

    /**
     * Herbruikbare methode voor optionele velden.
     */
    private function assertValidOptionalField(
        string $field,
        string $value
    ): void {
        $client = $this->client;

        $crawler = $client->request('GET', '/modify');

        $form = $crawler->selectButton('Wijzigingen opslaan')->form();

        $form[$field] = $value;

        $client->submit($form);

        self::assertResponseRedirects();

        $client->followRedirect();

        self::assertSelectorTextContains(
            '.account-flash-success',
            'Je wijzigingen zijn succesvol opgeslagen!'
        );
    }
}
