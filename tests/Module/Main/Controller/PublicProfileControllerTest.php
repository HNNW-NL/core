<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileSkill;
use App\Entity\Account\Skill;
use App\Enum\SkillProficiencyLevel;
use App\Enum\ProfileVisibilityPermission;
use App\Enum\VisibilityLevel;
use App\Repository\Account\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicProfileControllerTest extends WebTestCase
{
    public function testPublicProfileOnlyRendersPublicProfileData(): void
    {
        $account = $this->createAccount('alice', 'public');
        $account->getSetting()
            ->setSectionVisibility(ProfileVisibilityPermission::HEADLINE, VisibilityLevel::PUBLIC)
            ->setSectionVisibility(ProfileVisibilityPermission::SKILLS, VisibilityLevel::PUBLIC)
            ->setSectionVisibility(ProfileVisibilityPermission::EXPERIENCE, VisibilityLevel::PUBLIC);
        $profile = $account->getProfile();
        $profile->setDisplayName('Alice Example');
        $profile->setDescription('Open biography');
        $profile->setLocation('Private location');
        $profile->setAvatarUrl('/avatars/alice.png');

        $skill = (new Skill())->setName('PHP');
        $profileSkill = (new ProfileSkill())
            ->setProfile($profile)
            ->setSkill($skill)
            ->setProficiencyLevel(SkillProficiencyLevel::ADVANCED);
        $profile->getProfileSkills()->add($profileSkill);

        $experience = (new ProfileExperience())
            ->setProfile($profile)
            ->setExternalOrganisationName('Example Org')
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

    public function testNonPublicProfileSectionsAreOmittedFromPublicResponse(): void
    {
        $account = $this->createAccount('bob', 'public');
        $profile = $account->getProfile();
        $profile->setDescription('PRIVATE BIOGRAPHY');
        $profile->getProfileSkills()->add(
            (new ProfileSkill())
                ->setProfile($profile)
                ->setSkill((new Skill())->setName('PRIVATE SKILL'))
        );
        $profile->getExperiences()->add(
            (new ProfileExperience())
                ->setProfile($profile)
                ->setExternalOrganisationName('PRIVATE ORGANISATION')
                ->setJobTitle('PRIVATE ROLE')
                ->setStartDate(new \DateTimeImmutable('2022-01-01'))
        );

        $client = static::createClient();
        $this->mockAccountRepository('bob', $account);
        $client->request('GET', '/profile/bob');

        $content = (string) $client->getResponse()->getContent();
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('PRIVATE BIOGRAPHY', $content);
        $this->assertStringNotContainsString('PRIVATE SKILL', $content);
        $this->assertStringNotContainsString('PRIVATE ORGANISATION', $content);
        $this->assertStringNotContainsString('PRIVATE ROLE', $content);
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
