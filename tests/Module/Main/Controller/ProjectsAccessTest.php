<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectsAccessTest extends WebTestCase
{
    public function testPublicProjectDoesNotExposeTeamLinksToVisitor(): void
    {
        $client = static::createClient();
        $this->setProjectRepository('public-project', $this->createProject());

        $client->request('GET', '/projects/public-project');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Public project');
        $this->assertSelectorNotExists('nav[aria-label="Projectteam"]');
        $this->assertStringNotContainsString('Private motivation', (string) $client->getResponse()->getContent());
    }

    #[DataProvider('teamRouteProvider')]
    public function testTeamRoutesHideProjectFromNonParticipant(string $route): void
    {
        $client = static::createClient();
        $this->setProjectRepository('private-project', $this->createProject(), true);
        $profileProvider = $this->createMock(CurrentProfileProvider::class);
        $profileProvider->expects($this->once())
            ->method('getProfile')
            ->willReturn(new Profile());
        static::getContainer()->set(CurrentProfileProvider::class, $profileProvider);
        $participantRepository = $this->createMock(ProjectParticipantRepository::class);
        $participantRepository->expects($this->once())
            ->method('isActiveParticipant')
            ->willReturn(false);
        static::getContainer()->set(ProjectParticipantRepository::class, $participantRepository);

        $client->request('GET', '/projects/private-project/' . $route);

        $this->assertResponseStatusCodeSame(404);
    }

    public static function teamRouteProvider(): array
    {
        return [
            'chat' => ['chat'],
            'participants' => ['participants'],
            'reviews' => ['reviews'],
            'tasks' => ['tasks'],
            'updates' => ['updates'],
            'work packages' => ['work-packages'],
        ];
    }

    public function testUnknownProjectSlugReturnsNotFound(): void
    {
        $client = static::createClient();
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findOnePublicBySlug')
            ->with('unknown-project')
            ->willReturn(null);
        static::getContainer()->set(ProjectRepository::class, $repository);

        $client->request('GET', '/projects/unknown-project');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testClosedProjectCannotBeOpenedForApplicationDirectly(): void
    {
        $client = static::createClient();
        $project = $this->createProject();
        $project->setEndDate(new \DateTimeImmutable('yesterday'));
        $this->setProjectRepository('closed-project', $project);

        $client->request('GET', '/projects/closed-project/apply');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testActiveParticipantGetsTeamLinksOnProjectDetail(): void
    {
        $client = static::createClient();
        $this->setProjectRepository('public-project', $this->createProject());
        $participantRepository = $this->createMock(ProjectParticipantRepository::class);
        $participantRepository->expects($this->once())
            ->method('isActiveParticipant')
            ->willReturn(true);
        static::getContainer()->set(ProjectParticipantRepository::class, $participantRepository);

        $account = (new Account())
            ->setUsername('participant')
            ->setEmail('participant@example.test');
        $profile = (new Profile())->setAccount($account);
        $account->setProfile($profile);
        $profileProvider = $this->createMock(CurrentProfileProvider::class);
        $profileProvider->expects($this->once())
            ->method('getProfile')
            ->willReturn($profile);
        static::getContainer()->set(CurrentProfileProvider::class, $profileProvider);
        $client->request('GET', '/projects/public-project');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav[aria-label="Projectteam"] a[href="/projects/public-project/chat"]');
    }

    private function createProject(): Project
    {
        $status = (new Status())->setName('published');
        $organisation = (new Organisation())->setName('Public organisation');

        $project = (new Project())
            ->setTitle('Public project')
            ->setSlug('public-project')
            ->setSummary('Public summary')
            ->setDescription('Public description')
            ->setStartDate(new \DateTimeImmutable('today'))
            ->setCapacity(null)
            ->setStatus($status)
            ->setVisibility('public')
            ->setOwnerOrganisation($organisation)
            ->setEndDate(null);
        $project->publish();

        return $project;
    }

    private function setProjectRepository(string $slug, Project $project, bool $teamRoute = false): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $lookupMethod = $teamRoute ? 'findOneVisibleBySlug' : 'findOnePublicBySlug';
        $repository->expects($this->once())
            ->method($lookupMethod)
            ->with($slug)
            ->willReturn($project);

        static::getContainer()->set(ProjectRepository::class, $repository);
    }
}
