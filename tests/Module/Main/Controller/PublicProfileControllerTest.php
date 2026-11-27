<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileSkill;
use App\Entity\Account\Skill;
use App\Repository\Account\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicProfileControllerTest extends WebTestCase
{
    public function testPublicProfileOnlyRendersPublicProfileData(): void
    {
        $account = $this->createAccount('alice', 'public');
        $profile = $account->getProfile();
        $profile->setDisplayName('Alice Example');
        $profile->setDescription('Open biography');
        $profile->setLocation('Private location');
        $profile->setAvatarUrl('/avatars/alice.png');

        $skill = (new Skill())->setName('PHP');
        $profileSkill = (new ProfileSkill())
            ->setProfile($profile)
            ->setSkill($skill)
            ->setProficiencyLevel('advanced');
        $profile->getProfileSkills()->add($profileSkill);

        $experience = (new ProfileExperience())
            ->setProfile($profile)
            ->setOrganisationName('Example Org')
            ->setJobTitle('Developer')
            ->setDescription('Public experience')
            ->setStartDate(new \DateTimeImmutable('2022-01-01'));
        $profile->getExperiences()->add($experience);

        $client = static::createClient();
        $this->mockAccountRepository('alice', $account);
        $client->request('GET', '/profile/alice');

        $this->assertResponseIsSuccessful();
        $cacheControl = (string) $client->getResponse()->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertSelectorTextContains('h1', 'Alice Example');
        $this->assertSelectorTextContains('body', 'Open biography');
        $this->assertSelectorTextContains('body', 'PHP');
        $this->assertSelectorTextContains('body', 'Example Org');
        $this->assertStringNotContainsString('Private location', (string) $client->getResponse()->getContent());
        $this->assertStringNotContainsString('alice@example.test', (string) $client->getResponse()->getContent());
        $this->assertStringNotContainsString('Laatst actief', (string) $client->getResponse()->getContent());
    }

    public function testPrivateProfileIsNotVisibleToAnotherVisitor(): void
    {
        $client = static::createClient();
        $this->mockAccountRepository('private-user', $this->createAccount('private-user', 'private'));
        $client->request('GET', '/profile/private-user');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUnknownProfileSlugReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->mockAccountRepository('missing-user', null);
        $client->request('GET', '/profile/missing-user');

        $this->assertResponseStatusCodeSame(404);
    }

    private function createAccount(string $username, string $visibility): Account
    {
        $account = (new Account())
            ->setUsername($username)
            ->setEmail($username . '@example.test');
        $profile = (new Profile())
            ->setAccount($account)
            ->setFirstName('First')
            ->setLastName('Last');
        $account->setProfile($profile);

        $settings = (new AccountSetting())
            ->setAccount($account)
            ->setProfileVisibility($visibility);
        $account->setSetting($settings);

        return $account;
    }

    private function mockAccountRepository(string $slug, ?Account $account): void
    {
        $repository = $this->createMock(AccountRepository::class);
        $repository->expects($this->once())
            ->method('findByUsername')
            ->with($slug)
            ->willReturn($account);

        static::getContainer()->set(AccountRepository::class, $repository);
    }
}
