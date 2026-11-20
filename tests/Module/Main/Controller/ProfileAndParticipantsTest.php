<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\AccountSetting;
use App\Entity\Account\Profile;
use App\Entity\Account\ProfileExperience;
use App\Entity\Account\ProfileSkill;
use App\Entity\Account\Skill;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Entity\Project\ProjectParticipant;
use App\Entity\Project\ProjectRole;
use App\Enum\ProfileVisibilityPermission;
use App\Enum\VisibilityLevel;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileAndParticipantsTest extends WebTestCase
{
    private array $owned = [];
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Account $owner;
    private Account $member;
    private Account $outsider;
    private Project $project;
    private string $runId;
    private array $fixtureStatuses = [];

    protected static function getKernelClass(): string { return Kernel::class; }

    private function persist(object $entity): void
    {
        if (!$this->em->contains($entity)) {
            $this->owned[] = [$entity::class, $entity->getId()];
            $this->em->persist($entity);
        }
    }

    private function fixtureStatus(string $scope, string $name): Status
    {
        $key = $scope.':'.$name;
        if (isset($this->fixtureStatuses[$key])) {
            return $this->fixtureStatuses[$key];
        }
        $status = $this->em->getRepository(Status::class)->findOneBy(['scope' => $scope, 'name' => $name])
            ?? (new Status())->setScope($scope)->setName($name)->setColourHex('#10b981');
        $this->persist($status);
        return $this->fixtureStatuses[$key] = $status;
    }

    private function account(string $name, bool $public): Account
    {
        $account = (new Account())->setUsername($name.'-'.$this->runId)->setEmail($name.'-'.$this->runId.'@example.test')
            ->setPasswordHash('unused')->setStatus($this->fixtureStatus('account', 'active'));
        $profile = (new Profile())->setAccount($account)->setFirstName($name)->setLastName('Test')->setDisplayName(ucfirst($name))
            ->setAvatarUrl('')->setDescription('Een biografie <script>alert(1)</script>')->setHeadline('Private headline');
        $setting = (new AccountSetting())->setAccount($account)->setProfileVisibility($public ? 'public' : 'private');
        $setting->setSectionVisibility(ProfileVisibilityPermission::SKILLS, VisibilityLevel::PUBLIC);
        $account->setProfile($profile)->setSetting($setting);
        foreach ([$account, $profile, $setting] as $entity) { $this->persist($entity); }
        return $account;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->runId = bin2hex(random_bytes(8));
        $this->fixtureStatus('ProjectApplication', 'accepted');
        $this->owner = $this->account('leader', true);
        $this->member = $this->account('member', false);
        $this->outsider = $this->account('outsider', false);
        $org = (new Organisation())->setName('Projectorganisatie')->setSlug('org-'.$this->runId)->setStatus($this->fixtureStatus('organisation', 'active'));
        $this->persist($org);
        $this->project = (new Project())->setOwnerAccount($this->owner)->setOwnerOrganisation($org)->setStatus($this->fixtureStatus('project', 'published'))
            ->setTitle('Samen bouwen')->setSlug('participants-'.$this->runId)->setVisibility('public')->setSummary('Test')->setDescription('Test')
            ->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
        $this->project->publish();
        $this->persist($this->project);
        foreach (['Developer', 'Designer'] as $name) {
            $role = (new ProjectRole())->setProject($this->project)->setName($name);
            $this->persist($role);
            $participant = (new ProjectParticipant())->setProject($this->project)->setProfile($this->member->getProfile())
                ->setRole($role)->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
            $participant->join();
            $this->persist($participant);
        }
        $former = (new ProjectParticipant())->setProject($this->project)->setProfile($this->outsider->getProfile())
            ->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
        $former->join();
        $former->leave();
        $this->persist($former);
        $application = (new ProjectApplication())->setProject($this->project)->setProfile($this->outsider->getProfile())
            ->setStatus($this->fixtureStatus('ProjectApplication', 'send'))->setMotivation('Geheime motivatie voor de aanvraag.');
        $this->persist($application);
        $skill = (new Skill())->setName('Databasevaardigheid-'.$this->runId)->setSlug('skill-'.$this->runId)->setCategory('Development');
        $this->persist($skill);
        $this->persist((new ProfileSkill())->setProfile($this->owner->getProfile())->setSkill($skill));
        $this->persist((new ProfileExperience())->setProfile($this->owner->getProfile())->setJobTitle('Private werkervaring')
            ->setExternalOrganisationName('Voorbeeldbedrijf')->setStartDate(new \DateTimeImmutable('2024-01-01')));
        $this->persist((new \App\Entity\Account\ProfileEducation())->setProfile($this->owner->getProfile())
            ->setInstitutionName('Opleiding uit database')->setDegree('Softwareontwikkeling')->setStartDate(new \DateTimeImmutable('2023-01-01')));
        $this->persist((new \App\Entity\Account\ProfileCertification())->setProfile($this->owner->getProfile())
            ->setName('Certificaat uit database')->setIssuer('Testinstituut')->setCredentialUrl('javascript:alert(1)'));
        $this->persist((new \App\Entity\Account\ProfileSocialLink())->setProfile($this->owner->getProfile())
            ->setLabel('Mijn website')->setUrl('https://example.test/profiel'));
        $review = (new \App\Entity\Account\Review())->setProfile($this->owner->getProfile())->setReviewerProfile($this->outsider->getProfile())
            ->setProject($this->project)->setStatus($this->fixtureStatus('review', 'active'))->setMessage('Openbare aanbeveling uit database');
        $review->makePublic();
        $this->persist($review);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        try {
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            foreach ($this->owned as [$class, $id]) {
                if ($class === ProjectParticipant::class && ($entity = $em->find($class, $id)) !== null) {
                    $em->remove($entity);
                }
            }
            $em->flush();
            $em->clear();
            foreach (array_reverse($this->owned) as [$class, $id]) {
                $entity = $em->find($class, $id);
                if ($entity !== null) { $em->remove($entity); $em->flush(); }
            }
        } finally {
            $this->owned = [];
            parent::tearDown();
        }
    }

    public function testPublicProfileRespectsVisibilityAndDatabaseChanges(): void
    {
        $path = '/profile/'.$this->owner->getUsername();
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Leader');
        self::assertSelectorNotExists('a[href="/account/modify"]');
        self::assertSelectorTextContains('.profile-skill-list', 'Databasevaardigheid');
        $html = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString($this->owner->getEmail(), $html);
        self::assertStringNotContainsString('Private werkervaring', $html);
        self::assertStringNotContainsString('Private headline', $html);
        self::assertStringNotContainsString('Opleiding uit database', $html);
        self::assertStringNotContainsString('Certificaat uit database', $html);
        self::assertStringNotContainsString('https://example.test/profiel', $html);
        self::assertStringNotContainsString('Openbare aanbeveling uit database', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $profile = $em->find(Profile::class, $this->owner->getProfile()->getId());
        $profile->setDisplayName('Bijgewerkte naam');
        $profile->getAccount()->getSetting()->setSectionVisibility(ProfileVisibilityPermission::EXPERIENCE, VisibilityLevel::PUBLIC);
        foreach ([ProfileVisibilityPermission::EDUCATION, ProfileVisibilityPermission::CERTIFICATIONS, ProfileVisibilityPermission::SOCIAL_LINKS] as $section) {
            $profile->getAccount()->getSetting()->setSectionVisibility($section, VisibilityLevel::PUBLIC);
        }
        $em->find(Account::class, $this->outsider->getId())->getSetting()->setProfileVisibility('public');
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('h1', 'Bijgewerkte naam');
        self::assertSelectorTextContains('.profile-experience', 'Private werkervaring');
        self::assertSelectorTextContains('#education', 'Opleiding uit database');
        self::assertSelectorTextContains('#licenses', 'Certificaat uit database');
        self::assertSelectorTextContains('#recommendations', 'Openbare aanbeveling uit database');
        self::assertSelectorExists('#pi-website a[href="https://example.test/profiel"]');
        self::assertSelectorNotExists('a[href^="javascript:"]');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(Account::class, $this->owner->getId())->getSetting()->setProfileVisibility('private');
        $em->flush();
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorNotExists('.profile-biography');
        $this->client->loginUser($this->owner);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profile-biography', 'Een biografie');
        $this->client->request('GET', '/profile/'.$this->member->getUsername());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/profile/not-found-'.$this->runId);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/profile/'.$this->member->getUsername());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/account/modify"]');
    }

    public function testParticipantsExcludeFormerMembersAndPrivateData(): void
    {
        $path = '/projects/'.$this->project->getSlug().'/participants';
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->owner);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.participant-card');
        self::assertSelectorTextContains('.participant-grid', 'Projectleider');
        self::assertSelectorTextContains('.participant-grid', 'Developer');
        self::assertSelectorTextContains('.participant-grid', 'Designer');
        self::assertSelectorNotExists('a[href="/profile/'.$this->member->getUsername().'"]');
        $html = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Geheime motivatie', $html);
        self::assertStringNotContainsString($this->member->getEmail(), $html);
        self::assertSelectorNotExists('.participant-card a[href="/profile/'.$this->outsider->getUsername().'"]');
        $this->client->loginUser($this->member);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/profile/'.$this->member->getUsername().'"]');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $project = $em->find(Project::class, $this->project->getId());
        $application = $em->getRepository(ProjectApplication::class)->findOneBy(['project' => $project]);
        $workflow = static::getContainer()->get(\App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler::class);
        $result = $workflow->acceptApplication($project, (string) $application->getId(), $project->getOwnerAccount()->getProfile());
        self::assertTrue($result['success']);
        $this->client->request('GET', $path);
        self::assertSelectorCount(3, '.participant-card');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy([
            'project' => $em->find(Project::class, $project->getId()),
            'profile' => $em->find(Profile::class, $this->outsider->getProfile()->getId()),
        ]);
        $participant->leave();
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorCount(2, '.participant-card');
    }
}
