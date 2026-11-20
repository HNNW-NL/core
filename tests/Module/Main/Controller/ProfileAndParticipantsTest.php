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
        $org = (new Organisation())->setName('Projectorganisatie')->setSlug('org-'.$this->runId)
            ->setLogoUrl('/uploads/organisation-logo.png')->setStatus($this->fixtureStatus('organisation', 'active'));
        $this->persist($org);
        $this->project = (new Project())->setOwnerAccount($this->owner)->setOwnerOrganisation($org)->setStatus($this->fixtureStatus('project', 'published'))
            ->setTitle('Samen bouwen')->setSlug('participants-'.$this->runId)->setVisibility('public')->setSummary('Test')->setDescription('Test')
            ->setCapacity(10)->setStartDate(new \DateTimeImmutable())->setEndDate(null);
        $this->project->publish();
        $this->persist($this->project);
        foreach (['Developer', 'Designer'] as $name) {
            $role = (new ProjectRole())->setProject($this->project)->setName($name)
                ->setPermissionsMask($name === 'Developer' ? \App\Security\ProjectPermission::VIEW->value : 0);
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
            ->setOrganisation($org)
            ->setExternalOrganisationName('Voorbeeldbedrijf')->setStartDate(new \DateTimeImmutable('2024-01-01')));
        $this->persist((new \App\Entity\Account\ProfileEducation())->setProfile($this->owner->getProfile())
            ->setInstitutionName('Opleiding uit database')->setDegree('Softwareontwikkeling')->setFieldOfStudy('Beveiligingsonderzoek')
            ->setOrganisation($org)->setStartDate(new \DateTimeImmutable('2023-01-01')));
        $this->persist((new \App\Entity\Account\ProfileCertification())->setProfile($this->owner->getProfile())
            ->setName('Certificaat uit database')->setIssuer('Testinstituut')->setExpiresOn(new \DateTimeImmutable('2030-12-01'))
            ->setCredentialUrl('javascript:alert(1)'));
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
        self::assertSelectorExists('link[rel="stylesheet"][href$="/assets/main/css/profile.css"]');
        self::assertSelectorExists('link[rel="stylesheet"][href$="/assets/main/css/home.css"]');
        self::assertSelectorExists('#inner-main > #profile #basic-info #p-avatar');
        foreach (['#p-cover #pc-img', '#pi-icon', '#pcin-verification', '#pci-pronouns'] as $selector) {
            self::assertSelectorExists('#basic-info '.$selector);
        }
        self::assertSelectorExists('#pcin-verification[hidden]');
        self::assertSelectorExists('#inner-main > #suggestions');
        self::assertSelectorTextContains('#pi-stats', '1 openbare projecten');
        self::assertSelectorTextContains('#profile-projects', 'Samen bouwen');
        self::assertSelectorExists('#pi-btns a[href="#profile-projects"]');
        self::assertSelectorNotExists('link[href$="/profile-responsive.css"], link[href$="/profile-navigation.css"]');
        self::assertSelectorNotExists('a[href="/account/modify"]');
        self::assertSelectorTextContains('.profile-skill-list', 'Databasevaardigheid');
        $html = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString($this->owner->getEmail(), $html);
        self::assertStringNotContainsString('Private werkervaring', $html);
        self::assertStringNotContainsString('Private headline', $html);
        self::assertStringNotContainsString('Opleiding uit database', $html);
        self::assertStringNotContainsString('Beveiligingsonderzoek', $html);
        self::assertStringNotContainsString('Geldig tot 12-2030', $html);
        self::assertStringNotContainsString('Certificaat uit database', $html);
        self::assertStringNotContainsString('https://example.test/profiel', $html);
        self::assertStringNotContainsString('Openbare aanbeveling uit database', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $profile = $em->find(Profile::class, $this->owner->getProfile()->getId());
        $profile->setDisplayName('Bijgewerkte naam');
        $profile->getAccount()->verifyEmail();
        $profile->getAccount()->getSetting()->setSectionVisibility(ProfileVisibilityPermission::EXPERIENCE, VisibilityLevel::PUBLIC);
        foreach ([ProfileVisibilityPermission::EDUCATION, ProfileVisibilityPermission::CERTIFICATIONS, ProfileVisibilityPermission::SOCIAL_LINKS] as $section) {
            $profile->getAccount()->getSetting()->setSectionVisibility($section, VisibilityLevel::PUBLIC);
        }
        $em->find(Account::class, $this->outsider->getId())->getSetting()->setProfileVisibility('public');
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('h1', 'Bijgewerkte naam');
        self::assertSelectorNotExists('#pcin-verification[hidden]');
        self::assertSelectorTextContains('.profile-experience', 'Private werkervaring');
        self::assertSelectorExists('#pc-companies img[src="/uploads/organisation-logo.png"]');
        self::assertSelectorExists('.profile-experience .ei-logo img[src="/uploads/organisation-logo.png"]');
        self::assertSelectorTextContains('#education', 'Opleiding uit database');
        self::assertSelectorTextContains('#education', 'Beveiligingsonderzoek');
        self::assertSelectorExists('#education .edui-logo img[src="/uploads/organisation-logo.png"]');
        self::assertSelectorTextContains('#licenses', 'Certificaat uit database');
        self::assertSelectorTextContains('#licenses', 'Geldig tot 12-2030');
        foreach (['.e-footer', '.edu-footer', '.l-footer', '.s-footer', '.suggestion-footer'] as $selector) {
            self::assertSelectorExists($selector);
        }
        foreach (['#pcin-name', '#pc-companies', '#experience', '#education', '#licenses', '#skills', '#recommendations'] as $selector) {
            self::assertSelectorExists('#profile '.$selector);
        }
        self::assertSelectorTextContains('#recommendations', 'Openbare aanbeveling uit database');
        self::assertSelectorTextNotContains('#recommendations', 'Private headline');
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

    public function testProfileShortcutRedirectsToTheSignedInAccount(): void
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/profile/'.$this->owner->getUsername());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Leader');
        foreach (['#experience', '#education', '#licenses', '#skills'] as $section) {
            self::assertSelectorExists($section);
        }
    }

    public function testParticipantsArePagedByPersonWithStableOrderAndAllRoles(): void
    {
        $roles = $this->em->getRepository(ProjectRole::class)->findBy(['project' => $this->project], ['name' => 'ASC']);
        $expected = [];
        for ($i = 0; $i < 23; ++$i) {
            $account = $this->account('paged-'.$i, true);
            $avatar = '/uploads/page-'.$i.'.png';
            $account->getProfile()->setDisplayName('Gelijke naam')->setAvatarUrl($avatar);
            $expected[$account->getProfile()->getId()->toRfc4122()] = $avatar;
            foreach ($roles as $role) {
                $participant = (new ProjectParticipant())->setProject($this->project)->setProfile($account->getProfile())
                    ->setRole($role)->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
                $participant->join();
                $this->persist($participant);
            }
        }
        $inactiveAccount = $this->account('inactive-page', false);
        $inactive = (new ProjectParticipant())->setProject($this->project)->setProfile($inactiveAccount->getProfile())
            ->setStatus($this->fixtureStatus('ProjectParticipant', 'inactive'));
        $inactive->join();
        $this->persist($inactive);
        $this->em->flush();
        ksort($expected, SORT_STRING);
        $expected = array_values($expected);

        $path = '/projects/'.$this->project->getSlug().'/participants';
        $this->client->loginUser($this->owner);
        $page = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.participants-heading', '24 deelnemers');
        self::assertSelectorCount(20, '.participant-card');
        self::assertSelectorTextContains('.project-leader', 'Leader');
        self::assertSelectorTextContains('.participant-card:nth-child(2)', 'Developer');
        self::assertSelectorTextContains('.participant-card:nth-child(2)', 'Designer');
        self::assertSelectorCount(20, '.participant-card a');
        $avatars = static fn ($crawler): array => $crawler->filter('.participant-card img')->each(static fn ($image): string => $image->attr('src'));
        self::assertSame(array_slice($expected, 0, 20), $avatars($page));

        $page = $this->client->click($page->selectLink('Volgende')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(4, '.participant-card');
        self::assertSelectorTextContains('.participants-pagination', 'Pagina 2 van 2');
        self::assertSelectorNotExists('.participants-pagination [rel="next"]');
        self::assertSelectorCount(3, '.participant-card a');
        self::assertSame(array_slice($expected, 20), $avatars($page));
        self::assertStringNotContainsString('Inactive-page', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Outsider', $this->client->getResponse()->getContent());

        $page = $this->client->request('GET', $path.'?page=999');
        self::assertSelectorCount(4, '.participant-card');
        self::assertSame(array_slice($expected, 20), $avatars($page));
        $page = $this->client->request('GET', $path.'?page=-1');
        self::assertSelectorCount(20, '.participant-card');
        self::assertSame(array_slice($expected, 0, 20), $avatars($page));
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', $path.'?page=2');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.participant-card');
    }

    public function testPublicProjectsArePagedWithoutDuplicateOrPrivateProjects(): void
    {
        $expected = [];
        for ($i = 0; $i < 26; ++$i) {
            $project = (new Project())->setOwnerAccount($this->owner)->setOwnerOrganisation($this->project->getOwnerOrganisation())
                ->setStatus($this->project->getStatus())->setTitle('Paginaproject')->setSlug(sprintf('profile-page-%02d-%s', $i, $this->runId))
                ->setVisibility($i === 24 ? 'private' : 'public')->setSummary('Test')->setDescription('Test')
                ->setCapacity(10)->setStartDate(new \DateTimeImmutable());
            if ($i !== 25) { $project->publish(); }
            $this->persist($project);
            if ($i < 24) { $expected[] = '/projects/'.$project->getSlug(); }
            if ($i === 0) {
                foreach (['Eerste rol', 'Tweede rol'] as $name) {
                    $role = (new ProjectRole())->setProject($project)->setName($name)->setPermissionsMask(1);
                    $this->persist($role);
                    $participant = (new ProjectParticipant())->setProject($project)->setProfile($this->owner->getProfile())
                        ->setRole($role)->setStatus($this->fixtureStatus('ProjectParticipant', 'active'));
                    $participant->join();
                    $this->persist($participant);
                }
            }
        }
        $this->em->flush();
        $expected[] = '/projects/'.$this->project->getSlug();
        $links = static fn ($crawler): array => $crawler->filter('#profile-projects li a')->each(static fn ($link): string => $link->attr('href'));
        $path = '/profile/'.$this->owner->getUsername();
        $page = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#pi-stats', '25 openbare projecten');
        self::assertSame(array_slice($expected, 0, 20), $links($page));
        $page = $this->client->click($page->selectLink('Volgende')->link());
        self::assertResponseIsSuccessful();
        self::assertSame(array_slice($expected, 20), $links($page));
        self::assertSelectorTextContains('.profile-project-pagination', 'Pagina 2 van 2');
        self::assertSelectorNotExists('.profile-project-pagination [rel="next"]');
        self::assertSelectorExists('#experience, #education, #licenses, #skills, #recommendations');
        self::assertSame(array_slice($expected, 20), $links($this->client->request('GET', $path.'?projects_page=999')));
        self::assertSame(array_slice($expected, 0, 20), $links($this->client->request('GET', $path.'?projects_page=-1')));
        $this->client->request('GET', '/profile/'.$this->member->getUsername().'?projects_page=2');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyPublicSectionsRemainVisibleWithoutExposingPrivateSections(): void
    {
        $setting = $this->member->getSetting()->setProfileVisibility('public');
        foreach ([ProfileVisibilityPermission::EXPERIENCE, ProfileVisibilityPermission::EDUCATION,
            ProfileVisibilityPermission::CERTIFICATIONS] as $section) {
            $setting->setSectionVisibility($section, VisibilityLevel::PUBLIC);
        }
        $this->em->flush();
        $this->client->request('GET', '/profile/'.$this->member->getUsername());
        self::assertResponseIsSuccessful();
        foreach (['#experience', '#education', '#licenses', '#skills'] as $selector) {
            self::assertSelectorExists($selector.' .profile-empty');
        }
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(Account::class, $this->member->getId())->getSetting()
            ->setSectionVisibility(ProfileVisibilityPermission::EXPERIENCE, VisibilityLevel::PRIVATE);
        $em->flush();
        $this->client->request('GET', '/profile/'.$this->member->getUsername());
        self::assertSelectorNotExists('#experience');
        self::assertSelectorExists('#education');
    }

    public function testPrivateProjectsAreNotPublishedOnTheProfile(): void
    {
        $this->project->setVisibility('private');
        $this->em->flush();
        $this->client->request('GET', '/profile/'.$this->owner->getUsername());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#pi-stats', '0 openbare projecten');
        self::assertSelectorNotExists('#profile-projects a');
        self::assertSelectorNotExists('.profile-project-pagination');
    }

    #[\PHPUnit\Framework\Attributes\TestWith([0])]
    #[\PHPUnit\Framework\Attributes\TestWith([2])]
    public function testActiveParticipantWithoutProjectViewCannotReadParticipants(int $permissions): void
    {
        foreach ($this->em->getRepository(ProjectRole::class)->findBy(['project' => $this->project]) as $role) {
            $role->setPermissionsMask($permissions);
        }
        $this->em->flush();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/projects/'.$this->project->getSlug().'/participants');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.participant-card');
        self::assertSelectorNotExists('.participants-heading');
    }

    public function testProjectViewStillRequiresActiveParticipationForThisList(): void
    {
        $inactive = $this->fixtureStatus('ProjectParticipant', 'inactive');
        foreach ($this->em->getRepository(ProjectParticipant::class)->findBy([
            'project' => $this->project, 'profile' => $this->member->getProfile(),
        ]) as $participant) {
            $participant->setStatus($inactive);
        }
        $this->em->flush();
        $voter = static::getContainer()->get(\App\Security\Voter\ProjectVoter::class);
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($this->member, 'main', $this->member->getRoles());
        self::assertSame(1, $voter->vote($token, $this->project, ['PROJECT_VIEW']));
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/projects/'.$this->project->getSlug().'/participants');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.participant-card');
    }

    public function testPrivateParticipantOnlyExposesProjectDisplayNameAndRoles(): void
    {
        $profile = $this->member->getProfile();
        $profile->setDisplayName('Naam binnen project')->setFirstName('VerborgenVoornaam')->setLastName('VerborgenAchternaam')
            ->setAvatarUrl('https://example.test/verborgen-avatar.png');
        $this->em->flush();
        $path = '/projects/'.$this->project->getSlug().'/participants';
        $this->client->loginUser($this->owner);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.participant-card', 'Naam binnen project');
        self::assertSelectorTextContains('.participant-card', 'Developer');
        $html = $this->client->getResponse()->getContent();
        foreach (['VerborgenVoornaam', 'VerborgenAchternaam', 'verborgen-avatar.png', $this->member->getUsername()] as $private) {
            self::assertStringNotContainsString($private, $html);
        }
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(Profile::class, $profile->getId())->setDisplayName('');
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('.participant-card h2', 'Deelnemer');
        self::assertStringNotContainsString('VerborgenVoornaam', $this->client->getResponse()->getContent());
    }

    public function testProjectLeaderOnlyCountsWhileActivelyParticipating(): void
    {
        $participant = (new ProjectParticipant())->setProject($this->project)->setProfile($this->owner->getProfile())
            ->setStatus($this->fixtureStatus('ProjectParticipant', 'inactive'));
        $participant->join();
        $this->persist($participant);
        $this->em->flush();
        $path = '/projects/'.$this->project->getSlug().'/participants';
        $this->client->loginUser($this->owner);
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('.participants-heading', '1 deelnemer');
        self::assertSelectorTextContains('.project-leader', 'Leader');
        self::assertSelectorCount(1, '.participant-card');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(ProjectParticipant::class, $participant->getId())->setStatus($em->getRepository(Status::class)
            ->findOneBy(['scope' => 'ProjectParticipant', 'name' => 'active']));
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('.participants-heading', '2 deelnemers');
        self::assertSelectorTextContains('.participant-card:first-child', 'Projectleider');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->find(ProjectParticipant::class, $participant->getId())->leave();
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorTextContains('.participants-heading', '1 deelnemer');
        self::assertSelectorTextContains('.project-leader', 'Leader');
    }

    public function testExperienceUsesOriginalCompanyTimelineForMultiplePositions(): void
    {
        $this->owner->getSetting()->setSectionVisibility(ProfileVisibilityPermission::EXPERIENCE, VisibilityLevel::PUBLIC);
        $this->persist((new ProfileExperience())->setProfile($this->owner->getProfile())
            ->setOrganisation($this->project->getOwnerOrganisation())->setJobTitle('Eerdere functie')
            ->setStartDate(new \DateTimeImmutable('2022-01-01'))->setEndDate(new \DateTimeImmutable('2023-12-31')));
        $this->em->flush();
        $this->client->request('GET', '/profile/'.$this->owner->getUsername());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#e-list .e-item');
        self::assertSelectorCount(2, '#e-list .ei-position');
        self::assertSelectorTextContains('#e-list .ei-timeline', 'Eerdere functie');
        self::assertSelectorTextContains('#e-list .ei-timeline', 'Private werkervaring');
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
        self::assertSelectorCount(1, '.participant-card');
        self::assertSelectorExists('link[rel="stylesheet"][href$="/assets/main/css/participants.css"]');
        self::assertSelectorNotExists('link[href$="/profile-responsive.css"], link[href$="/profile-navigation.css"]');
        self::assertSelectorTextContains('.project-leader', 'Projectleider');
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
        $this->client->loginUser($this->outsider);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.participant-card');
        $this->client->loginUser($this->member);
        $this->client->request('GET', $path);
        self::assertSelectorCount(2, '.participant-card');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $participant = $em->getRepository(ProjectParticipant::class)->findOneBy([
            'project' => $em->find(Project::class, $project->getId()),
            'profile' => $em->find(Profile::class, $this->outsider->getProfile()->getId()),
        ]);
        $participant->leave();
        $em->flush();
        $this->client->request('GET', $path);
        self::assertSelectorCount(1, '.participant-card');
    }
}
