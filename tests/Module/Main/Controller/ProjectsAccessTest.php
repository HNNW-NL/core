<?php

namespace App\Tests\Module\Main\Controller;

use App\Entity\Common\Status;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Repository\Project\ProjectRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ProjectsAccessTest extends WebTestCase
{
    public function testPublicProjectDoesNotExposeTeamLinksToVisitor(): void
    {
        $client = static::createClient();
        $this->setProjectRepository('public-project', $this->createProject());

        $client->request('GET', '/projects/public-project');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Public project');
        $this->assertSelectorNotExists('nav[aria-label="Projectnavigatie"]');
        $this->assertStringNotContainsString('Private motivation', (string) $client->getResponse()->getContent());
    }

    #[DataProvider('teamRouteProvider')]
    public function testTeamRoutesRejectAnonymousVisitors(string $route): void
    {
        $client = static::createClient();
        $this->setProjectRepository('private-project', $this->createProject());

        $client->request('GET', '/projects/private-project/' . $route);

        $this->assertResponseRedirects('/auth/login');
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
            ->method('findOneVisibleBySlug')
            ->with('unknown-project')
            ->willReturn(null);
        static::getContainer()->set(ProjectRepository::class, $repository);

        $client->request('GET', '/projects/unknown-project');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testClosedProjectApplicationRouteRequiresLogin(): void
    {
        $client = static::createClient();
        $project = $this->createProject();
        $project->setEndDate(new \DateTimeImmutable('yesterday'));
        $this->setProjectRepository('closed-project', $project);

        $client->request('GET', '/projects/closed-project/apply');

        $this->assertResponseRedirects('/auth/login');
    }

    public function testAuthorizedParticipantGetsTeamNavigation(): void
    {
        $client = static::createClient();
        $project = $this->createProject();
        $this->setProjectRepository('public-project', $project);

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturnCallback(static fn (string $attribute): bool => $attribute === 'PROJECT_VIEW');
        static::getContainer()->set('security.authorization_checker', $authorizationChecker);

        $client->request('GET', '/projects/public-project');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav[aria-label="Projectnavigatie"] a[href="/projects/public-project/work-packages"]');
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

    private function setProjectRepository(string $slug, Project $project): void
    {
        $repository = $this->createMock(ProjectRepository::class);
        $repository->expects($this->once())
            ->method('findOneVisibleBySlug')
            ->with($slug)
            ->willReturn($project);

        static::getContainer()->set(ProjectRepository::class, $repository);
    }
}
