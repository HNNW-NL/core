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
        $this->persist((new ProfileSkill())->setProfile($this->owner->getProfile())->setSkill($skill)
            ->setProficiencyLevel(\App\Enum\SkillProficiencyLevel::ADVANCED)->setYearsOfExperience(3));
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
            // Foreign-key dependency order, independent of when each unique fixture was created.
            $order = array_flip([
                ProjectParticipant::class, \App\Entity\Account\Review::class, ProjectApplication::class, ProjectRole::class,
                \App\Entity\Account\ProfileSocialLink::class, \App\Entity\Account\ProfileCertification::class,
                \App\Entity\Account\ProfileEducation::class, ProfileExperience::class, ProfileSkill::class,
                Project::class, Organisation::class, Profile::class, AccountSetting::class, Account::class, Skill::class, Status::class,
            ]);
            $records = $this->owned;
            usort($records, static fn ($a, $b): int => ($order[$a[0]] ?? 0) <=> ($order[$b[0]] ?? 0));
            foreach ($records as [$class, $id]) {
                $entity = $em->find($class, $id);
                if ($entity !== null) { $em->remove($entity); $em->flush(); }
            }
        } finally {
            $this->owned = [];
            parent::tearDown();
        }
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['send', 'Ingediend'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['accepted', 'Geaccepteerd'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['rejected', 'Afgewezen'])]
    public function testApplicationLabelsAndFiltersRequireTheSameScope(string $name, string $label): void
    {
        $application = $this->em->getRepository(ProjectApplication::class)->findOneBy(['profile' => $this->outsider->getProfile()]);
        $service = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        $repository = static::getContainer()->get(\App\Repository\Project\AccountApplicationRepository::class);
        foreach (['ProjectApplication', 'pRoJeCtApPlIcAtIoN', 'ProjectParticipant'] as $scope) {
            $application->setStatus($this->fixtureStatus($scope, $name));
            $this->em->flush();
            $valid = strcasecmp($scope, 'ProjectApplication') === 0;
            self::assertSame($valid ? $label : 'Onbekend', $service->label($application));
            self::assertSame($valid ? 1 : 0, $repository->pageForProfile($this->outsider->getProfile(), $label, 1)['total']);
        }
    }

    public function testProfileSectionsArePaginatedAndOnlyFeaturedSkillsAreHighlighted(): void
    {
        $profile = $this->owner->getProfile();
        $service = static::getContainer()->get(\App\Module\Main\Service\PublicProfileService::class);
        self::assertSame([], $service->load($this->owner->getUsername(), $this->owner)['topSkills']);
        for ($i = 0; $i < 7; ++$i) {
            $this->persist((new ProfileExperience())->setProfile($profile)->setJobTitle('Functie '.$i)
                ->setExternalOrganisationName('Werkgever '.$i)->setStartDate(new \DateTimeImmutable('2025-01-01')));
            $this->persist((new \App\Entity\Account\ProfileEducation())->setProfile($profile)
                ->setInstitutionName('School '.$i)->setStartDate(new \DateTimeImmutable('2025-01-01')));
            $this->persist((new \App\Entity\Account\ProfileCertification())->setProfile($profile)->setName('Certificaat '.$i)->setIssuer('Instelling'));
            $this->persist((new \App\Entity\Account\ProfileSocialLink())->setProfile($profile)
                ->setLabel('Website '.$i)->setUrl('https://example.test/'.$i));
            $skill = (new Skill())->setName('Vaardigheid '.$i.'-'.$this->runId)->setSlug('page-skill-'.$i.'-'.$this->runId)->setCategory('Development');
            $this->persist($skill);
            $record = (new ProfileSkill())->setProfile($profile)->setSkill($skill);
            if ($i === 6) { $record->feature(); }
            $this->persist($record);
        }
        $this->em->flush();
        $sections = ['experience', 'education', 'certificates', 'skills', 'socialLinks'];
        $first = $service->load($this->owner->getUsername(), $this->owner);
        $second = $service->load($this->owner->getUsername(), $this->owner, 1, array_fill_keys($sections, 2));
        foreach ($sections as $section) {
            self::assertCount(5, $first[$section], $section);
            self::assertCount(3, $second[$section], $section);
            self::assertEquals(['total' => 8, 'page' => 2, 'pages' => 2], $second['pagination'][$section]);
            self::assertSame([], array_intersect(array_map('serialize', $first[$section]), array_map('serialize', $second[$section])));
        }
        self::assertSame([['name' => 'Vaardigheid 6-'.$this->runId]], $first['topSkills']);
        self::assertSame($first['topSkills'], $second['topSkills']);
        self::assertEquals($second, $service->load($this->owner->getUsername(), $this->owner, 1, array_fill_keys($sections, PHP_INT_MAX)));
        self::assertEquals($first, $service->load($this->owner->getUsername(), $this->owner, 1, array_fill_keys($sections, -1)));
        $this->client->loginUser($this->owner);
        $page = $this->client->request('GET', '/profile/'.$this->owner->getUsername().'?education_page=2&skills_page=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '#s-list article');
        self::assertSelectorCount(3, '#edu-list article');
        self::assertSelectorTextContains('#top-skills', 'Vaardigheid 6-');
        self::assertSelectorTextNotContains('#top-skills', 'Databasevaardigheid');
        $this->client->click($page->filter('#skills a[rel="next"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '#s-list article');
        self::assertSelectorCount(3, '#edu-list article');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $em->find(Account::class, $this->owner->getId());
        $owner->getSetting()->setSectionVisibility(ProfileVisibilityPermission::SKILLS, VisibilityLevel::PRIVATE);
        $em->flush();
        $hidden = static::getContainer()->get(\App\Module\Main\Service\PublicProfileService::class)->load($owner->getUsername(), null);
        self::assertSame([], $hidden['skills']);
        self::assertSame([], $hidden['topSkills']);
        self::assertArrayNotHasKey('skills', $hidden['pagination']);
    }

    public function testSubmissionRefreshesAndRestoresTheExistingWithdrawnRecord(): void
    {
        $this->outsider->verifyEmail();
        $application = $this->em->getRepository(ProjectApplication::class)->findOneBy(['profile' => $this->outsider->getProfile()]);
        $this->em->flush();
        // Simulate a committed concurrent change while the Identity Map still contains the old object.
        $this->em->getConnection()->executeStatement('UPDATE project_applications SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id',
            ['id' => $application->getId()], ['id' => 'ulid']);
        self::assertNull($application->getDeletedAt());
        $service = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        $submission = $service->submitWithResult($this->project, $this->outsider, $this->outsider->getProfile(), 'Een volledig nieuwe geldige motivatie.');
        self::assertSame($application, $submission->application);
        self::assertTrue($submission->created);
        self::assertNull($application->getDeletedAt());
        self::assertNull($application->getReviewedAt());
        self::assertNull($application->getReviewerProfile());
        self::assertNull($application->getReview());
        self::assertSame('Ingediend', $service->label($application));
        self::assertSame('Een volledig nieuwe geldige motivatie.', $application->getMotivation());
        self::assertSame(1, $this->em->getRepository(ProjectApplication::class)->count(['project' => $this->project, 'profile' => $this->outsider->getProfile()]));
        self::assertFalse($service->submitWithResult($this->project, $this->outsider, $this->outsider->getProfile(), 'Een dubbele geldige motivatie.')->created);
    }

    public function testConcurrentReviewIsRefreshedAndRejectedWithdrawalKeepsTheManagerOpen(): void
    {
        $this->outsider->verifyEmail();
        $this->em->flush();
        $application = $this->em->getRepository(ProjectApplication::class)->findOneBy(['profile' => $this->outsider->getProfile()]);
        $this->em->getConnection()->executeStatement('UPDATE project_applications SET status_id = :status WHERE id = :id',
            ['status' => $this->fixtureStatus('ProjectApplication', 'accepted')->getId(), 'id' => $application->getId()], ['status' => 'ulid', 'id' => 'ulid']);
        $service = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        try {
            $service->submitWithResult($this->project, $this->outsider, $this->outsider->getProfile(), 'Een geldige motivatie voor deze aanvraag.');
            self::fail('An already reviewed request must not be resubmitted.');
        } catch (\App\Module\Main\Exception\ProjectApplicationBlocked $exception) {
            self::assertStringContainsString('behandeld', $exception->getMessage());
        }
        try {
            $service->withdraw($application, $this->outsider->getProfile());
            self::fail('An accepted request must not be withdrawn.');
        } catch (\DomainException) {
            self::assertTrue($this->em->isOpen());
            self::assertSame('Geaccepteerd', $service->label($application));
            self::assertNotNull($this->em->getRepository(ProjectApplication::class)->find($application->getId()));
        }
    }

    public function testExperiencePaginationKeepsOrganisationGroupsTogether(): void
    {
        $profile = $this->owner->getProfile();
        for ($i = 0; $i < 7; ++$i) {
            $this->persist((new ProfileExperience())->setProfile($profile)->setOrganisation($this->project->getOwnerOrganisation())
                ->setJobTitle('Groepsfunctie '.$i)->setStartDate(new \DateTimeImmutable('2026-01-01')));
        }
        for ($i = 0; $i < 6; ++$i) {
            $this->persist((new ProfileExperience())->setProfile($profile)->setExternalOrganisationName('Andere werkgever '.$i)
                ->setJobTitle('Andere functie '.$i)->setStartDate(new \DateTimeImmutable('2025-01-01')));
        }
        $this->em->flush();
        $service = static::getContainer()->get(\App\Module\Main\Service\PublicProfileService::class);
        $first = $service->load($this->owner->getUsername(), $this->owner);
        $second = $service->load($this->owner->getUsername(), $this->owner, 1, ['experience' => 2]);
        self::assertSame(7, $first['pagination']['experience']['total']);
        self::assertCount(5, $first['experienceGroups']);
        self::assertCount(2, $second['experienceGroups']);
        $group = $first['experienceGroups'][0];
        self::assertSame('Projectorganisatie', $group['company']);
        self::assertSame(8, $group['total']);
        self::assertCount(5, $group['positions']);
        self::assertNotContains('Projectorganisatie', array_column($second['experienceGroups'], 'company'));
        $more = $service->load($this->owner->getUsername(), $this->owner, 1, [$group['paginationKey'] => 2]);
        self::assertCount(3, $more['experienceGroups'][0]['positions']);
        self::assertSame($group['company'], $more['experienceGroups'][0]['company']);
        $this->client->loginUser($this->owner);
        $page = $this->client->request('GET', '/profile/'.$this->owner->getUsername());
        self::assertResponseIsSuccessful();
        $link = $page->filter('#experience .e-item:first-child nav a[rel="next"]')->link();
        $this->client->click($link);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '#experience .e-item:first-child .ei-position');
    }

    public function testRecommendationPrivacyIsAppliedBeforeCountingAndPagination(): void
    {
        $publicPerson = $this->account('public-reviewer', true)->getProfile();
        foreach ([true, false] as $public) {
            for ($i = 0; $i < ($public ? 6 : 12); ++$i) {
                $other = $public ? $publicPerson : $this->outsider->getProfile();
                foreach ([true, false] as $received) {
                    $review = (new \App\Entity\Account\Review())->setProfile($received ? $this->owner->getProfile() : $other)
                        ->setReviewerProfile($received ? $other : $this->owner->getProfile())->setProject($this->project)
                        ->setStatus($this->fixtureStatus('review', 'active'))->setMessage(($public ? 'Zichtbaar ' : 'Verborgen ').$i);
                    $review->makePublic();
                    $this->persist($review);
                }
            }
        }
        $this->em->flush();
        $service = static::getContainer()->get(\App\Module\Main\Service\PublicProfileService::class);
        $first = $service->load($this->owner->getUsername(), null);
        $second = $service->load($this->owner->getUsername(), null, 1, ['receivedRecommendations' => 2, 'givenRecommendations' => 2]);
        foreach (['receivedRecommendations', 'givenRecommendations'] as $key) {
            self::assertSame(6, $first['pagination'][$key]['total']);
            self::assertCount(5, $first[$key]);
            self::assertCount(1, $second[$key]);
            foreach ([...$first[$key], ...$second[$key]] as $review) {
                self::assertStringStartsWith('Zichtbaar ', $review['text']);
            }
        }
        $page = $this->client->request('GET', '/profile/'.$this->owner->getUsername().'?givenRecommendations_page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#rt-given-content .r-item');
        self::assertSelectorExists('#rt-given[checked]');
        self::assertCount(1, $page->filter('#rt-received-content nav a[rel="next"]'));
        self::assertStringNotContainsString('Verborgen ', $this->client->getResponse()->getContent());
        $page = $this->client->click($page->filter('#rt-given-content nav a[rel="prev"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#rt-given[checked]');
        self::assertSelectorCount(5, '#rt-given-content .r-item');
        $this->client->click($page->filter('#rt-received-content nav a[rel="next"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#rt-received[checked]');
        self::assertSelectorCount(1, '#rt-received-content .r-item');
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['active', true, true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['published', true, true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['applications_closed', true, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['completed', true, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['archived', false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['withdrawn', false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['draft', false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['open', false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['actief', false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['published', false, false, 'ProjectApplication'])]
    public function testPublicProjectsAndApplicationsUseTheTeamOneLifecycle(string $status, bool $public, bool $open, string $scope = 'project'): void
    {
        $candidate = $this->account('lifecycle-applicant', true);
        $candidate->verifyEmail();
        $this->project->setStatus($this->fixtureStatus($scope, $status));
        // Retain publication timestamp and visibility to exercise stale publication metadata.
        $this->project->publish();
        $this->em->flush();
        $profiles = static::getContainer()->get(\App\Module\Main\Service\PublicProfileService::class);
        $data = $profiles->load($this->owner->getUsername(), null);
        self::assertSame($public ? 1 : 0, $data['publicProjectCount']);
        self::assertCount($public ? 1 : 0, $data['projects']);
        $applications = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        self::assertSame($open, $applications->check($this->project, $candidate->getProfile()) === null);
        $dto = new \App\Module\Main\DTO\ProjectApplyDto();
        $dto->motivation = 'Een geldige motivatie voor deze lifecyclecontrole.';
        $result = static::getContainer()->get(\App\Module\Main\Service\ProjectApplyService::class)->apply($this->project, $candidate->getProfile(), $dto);
        self::assertSame($open, $result->isSuccessful());
        if ($result->isSuccessful()) { $this->owned[] = [ProjectApplication::class, $result->application->getId()]; }
        $this->client->request('GET', '/projects/'.$this->project->getSlug());
        if ($public) {
            self::assertResponseIsSuccessful();
        } else {
            self::assertResponseRedirects('/auth/login');
        }

    }

    public function testDeletedOwnerOrganisationCannotReceiveApplicationsOrAppearOnProfiles(): void
    {
        $candidate = $this->account('deleted-org-applicant', true);
        $candidate->verifyEmail();
        $this->member->getSetting()->setProfileVisibility('public');
        $this->project->getOwnerOrganisation()->softDelete();
        $this->em->flush();
        $service = static::getContainer()->get(\App\Module\Main\Service\PublicProfileService::class);
        foreach ([$this->owner, $this->member] as $account) {
            $data = $service->load($account->getUsername(), null);
            self::assertSame(0, $data['publicProjectCount']);
            self::assertSame([], $data['projects']);
        }
        $applications = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        self::assertSame(\App\Module\Main\DTO\ProjectApplyStatus::NotOpen, $applications->check($this->project, $candidate->getProfile())->status);
        try {
            $applications->submit($this->project, $candidate, $candidate->getProfile(), 'Een geldige motivatie bij een verwijderde organisatie.');
            self::fail('A deleted organisation cannot receive applications.');
        } catch (\App\Module\Main\Exception\ProjectApplicationBlocked) {
            self::assertTrue($this->em->isOpen());
            self::assertSame(0, $this->em->getRepository(ProjectApplication::class)->count(['profile' => $candidate->getProfile()]));
        }
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['accepted', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['rejected', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['send', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['accepted', false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['rejected', false, false])]
    public function testSoftDeletedReviewedApplicationsCannotBeRestored(string $status, bool $pending, bool $hasReview = true): void
    {
        $this->outsider->verifyEmail();
        $application = $this->em->getRepository(ProjectApplication::class)->findOneBy(['profile' => $this->outsider->getProfile()]);
        $application->setStatus($this->fixtureStatus('ProjectApplication', $status));
        if ($hasReview) {
            $application->setReviewerProfile($this->owner->getProfile())->setReview('Historisch oordeel');
            $application->markAsReviewed();
        }
        $application->softDelete();
        $this->em->flush();
        $this->em->refresh($application);
        $reviewedAt = $application->getReviewedAt();
        $service = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        self::assertSame($pending, $service->pending($application));
        self::assertFalse($service->canResubmit($application));
        try {
            $service->submit($this->project, $this->outsider, $this->outsider->getProfile(), 'Een nieuwe motivatie mag het oordeel niet wissen.');
            self::fail('Reviewed history must not be restored.');
        } catch (\App\Module\Main\Exception\ProjectApplicationBlocked) {
            self::assertNotNull($application->getDeletedAt());
            self::assertEquals($reviewedAt, $application->getReviewedAt());
            self::assertSame($hasReview ? 'Historisch oordeel' : null, $application->getReview());
            self::assertSame($hasReview ? $this->owner->getProfile()->getId()->toString() : null, $application->getReviewerProfile()?->getId()->toString());
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
        self::assertSelectorTextContains('.profile-skill-list .si-context', 'Gevorderd · 3 jaar ervaring');
        self::assertSelectorNotExists('.si-endorsements');
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
        foreach (['.suggestion-footer'] as $selector) {
            self::assertSelectorExists($selector);
        }
        self::assertSelectorNotExists('.profile-project-pagination');
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
        self::assertSame(-1, $voter->vote($token, $this->project, ['PROJECT_VIEW']));
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/projects/'.$this->project->getSlug().'/participants');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('.participant-card');
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectParticipant', 'active', true, false, true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['projectparticipant', 'actief', true, false, true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectParticipant', 'inactive', true, false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectApplication', 'active', true, false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectParticipant', 'active', false, false, false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['ProjectParticipant', 'active', true, true, false])]
    public function testActiveMembershipDefinitionIsConsistent(string $scope, string $status, bool $joined, bool $left, bool $allowed): void
    {
        $candidate = $this->account('rights-matrix', false);
        $role = $this->em->getRepository(ProjectRole::class)->findOneBy(['project' => $this->project, 'name' => 'Developer']);
        $participant = (new ProjectParticipant())->setProject($this->project)->setProfile($candidate->getProfile())->setRole($role)
            ->setStatus($this->fixtureStatus($scope, $status));
        if ($joined) { $participant->join(); }
        if ($left) { $participant->leave(); }
        $this->persist($participant);
        $this->em->flush();
        $repository = static::getContainer()->get(\App\Repository\Project\ProjectParticipantRepository::class);
        self::assertSame($allowed, $repository->isActiveParticipant($this->project, $candidate->getProfile()));
        self::assertCount($allowed ? 1 : 0, $repository->findActiveParticipantsByProjectAndProfile($this->project, $candidate->getProfile()));
        self::assertSame($allowed, $repository->findLatestActiveParticipantByProjectAndProfile($this->project, $candidate->getProfile()) !== null);
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($candidate, 'main', $candidate->getRoles());
        self::assertSame($allowed ? 1 : -1, static::getContainer()->get(\App\Security\Voter\ProjectVoter::class)->vote($token, $this->project, ['PROJECT_VIEW']));
        $this->client->loginUser($candidate);
        $this->client->request('GET', '/projects/'.$this->project->getSlug().'/participants');
        self::assertResponseStatusCodeSame($allowed ? 200 : 403);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['unverified'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['owner'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['closed'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['expired'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['private'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['wrong-scope'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['active'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['inactive'])]
    public function testGetAndBothSubmissionServicesUseTheSameRules(string $scenario): void
    {
        $candidate = $this->account('eligibility', false);
        if ($scenario !== 'unverified') { $candidate->verifyEmail(); }
        if ($scenario === 'owner') { $this->project->setOwnerAccount($candidate); }
        if ($scenario === 'closed') { $this->project->setStatus($this->fixtureStatus('project', 'closed')); }
        if ($scenario === 'wrong-scope') { $this->project->setStatus($this->fixtureStatus('ProjectApplication', 'active')); }
        if ($scenario === 'expired') { $this->project->setEndDate(new \DateTimeImmutable('-1 day')); }
        if ($scenario === 'private') { $this->project->setVisibility('private'); }
        if (in_array($scenario, ['active', 'inactive'], true)) {
            $participant = (new ProjectParticipant())->setProject($this->project)->setProfile($candidate->getProfile())
                ->setStatus($this->fixtureStatus('ProjectParticipant', $scenario));
            $participant->join(); $this->persist($participant);
        }
        $this->em->flush();
        $central = static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class);
        $facade = static::getContainer()->get(\App\Module\Main\Service\ProjectApplyService::class);
        $handler = static::getContainer()->get(\App\Module\Main\Handler\ProjectApplyHandler::class);
        $blocked = $central->check($this->project, $candidate->getProfile());
        self::assertSame($blocked?->status, $facade->check($this->project, $candidate->getProfile())?->status);
        self::assertSame($blocked?->message, $handler->check($this->project, $candidate->getProfile())?->message);
        self::assertSame($scenario === 'inactive', $blocked === null);
        $dto = new \App\Module\Main\DTO\ProjectApplyDto(); $dto->motivation = 'Een geldige motivatie voor de centrale controle.';
        $result = $facade->apply($this->project, $candidate->getProfile(), $dto);
        self::assertSame($scenario === 'inactive', $result->isSuccessful());
        if ($scenario === 'unverified') {
            self::assertSame(\App\Module\Main\DTO\ProjectApplyStatus::EmailNotVerified, $result->status);
        }
        if ($result->isSuccessful()) { $this->owned[] = [ProjectApplication::class, $result->application->getId()]; }
        self::assertSame($scenario === 'inactive' ? 1 : 0,
            $this->em->getRepository(ProjectApplication::class)->count(['project' => $this->project, 'profile' => $candidate->getProfile()]));
        if (in_array($scenario, ['unverified', 'closed', 'expired', 'wrong-scope', 'inactive'], true)) {
            $this->client->loginUser($candidate);
            $page = $this->client->request('GET', '/projects/'.$this->project->getSlug().'/apply');
            self::assertResponseStatusCodeSame(in_array($scenario, ['closed', 'wrong-scope'], true) ? 403 : 200);
            self::assertCount(0, $page->filter('form[name="project_apply"]'));
        }
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['accept'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['reject'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['withdraw'])]
    public function testDecisionsAndAccessStaySynchronized(string $decision): void
    {
        $this->fixtureStatus('ProjectApplication', 'rejected');
        $former = $this->em->getRepository(ProjectParticipant::class)->findOneBy(['project' => $this->project, 'profile' => $this->outsider->getProfile()]);
        $former->setRole($this->em->getRepository(ProjectRole::class)->findOneBy(['project' => $this->project, 'name' => 'Developer']));
        $this->em->flush();
        $application = $this->em->getRepository(ProjectApplication::class)->findOneBy(['project' => $this->project, 'profile' => $this->outsider->getProfile()]);
        $workflow = static::getContainer()->get(\App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler::class);
        if ($decision === 'accept') {
            self::assertTrue($workflow->acceptApplication($this->project, (string) $application->getId(), $this->owner->getProfile())['success']);
            self::assertFalse($workflow->acceptApplication($this->project, (string) $application->getId(), $this->owner->getProfile())['success']);
        } elseif ($decision === 'reject') {
            self::assertTrue($workflow->rejectApplication($this->project, (string) $application->getId(), 'Niet passend voor dit project.', $this->owner->getProfile())['success']);
        } else {
            static::getContainer()->get(\App\Module\Main\Service\ProjectApplicationService::class)->withdraw($application, $this->outsider->getProfile());
        }
        self::assertSame(1, $this->em->getRepository(ProjectParticipant::class)->count(['project' => $this->project, 'profile' => $this->outsider->getProfile()]));
        $repository = static::getContainer()->get(\App\Repository\Project\ProjectParticipantRepository::class);
        self::assertSame($decision === 'accept', $repository->isActiveParticipant($this->project, $this->outsider->getProfile()));
        $token = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($this->outsider, 'main', $this->outsider->getRoles());
        $voter = static::getContainer()->get(\App\Security\Voter\ProjectVoter::class);
        self::assertSame($decision === 'accept' ? 1 : -1, $voter->vote($token, $this->project, ['PROJECT_VIEW']));
        $page = static::getContainer()->get(\App\Module\Main\Service\ProjectParticipantsService::class)->load($this->project, $this->owner);
        self::assertSame($decision === 'accept' ? 2 : 1, $page['total']);
        $label = match ($decision) { 'accept' => 'Geaccepteerd', 'reject' => 'Afgewezen', default => 'Ingetrokken' };
        self::assertSame(1, static::getContainer()->get(\App\Repository\Project\AccountApplicationRepository::class)
            ->pageForProfile($this->outsider->getProfile(), $label, 1)['total']);
        if ($decision === 'accept') {
            $former->leave(); $this->em->flush();
            self::assertSame(-1, $voter->vote($token, $this->project, ['PROJECT_VIEW']));
        }
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
