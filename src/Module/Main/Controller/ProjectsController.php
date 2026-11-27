<?php

namespace App\Module\Main\Controller;

use App\Module\Main\Handler\WorkPackageTaskEnrollmentHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRepository;
use App\Module\Main\Handler\GetProjectWorkPackagesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'main.projects.')]
final class ProjectsController extends AbstractController
{
    private const PROJECTS_PER_PAGE = 12;

    #[Route('', name: 'home', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository): Response
    {
        $query = $request->query->all();
        $errors = [];

        $rawSearch = $query['q'] ?? '';
        if (!is_string($rawSearch) || preg_match('/\A.{0,100}\z/us', trim($rawSearch)) !== 1) {
            $errors[] = 'Gebruik een zoekterm van maximaal 100 tekens.';
            $search = '';
        } else {
            $search = trim($rawSearch);
        }

        $rawRemoteFilter = $query['remote'] ?? '';
        if (!is_string($rawRemoteFilter) || !in_array($rawRemoteFilter, ['', 'yes', 'no'], true)) {
            $errors[] = 'De gekozen projectfilter is ongeldig.';
            $remoteFilter = '';
        } else {
            $remoteFilter = $rawRemoteFilter;
        }

        $rawPage = $query['page'] ?? '1';
        $requestedPage = is_string($rawPage)
            ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($requestedPage === false) {
            $errors[] = 'Het paginanummer is ongeldig. Je ziet pagina 1.';
            $requestedPage = 1;
        }

        $projects = $projectRepository->findPublicPage(
            $search,
            $remoteFilter === 'yes' ? true : ($remoteFilter === 'no' ? false : null),
            $requestedPage,
            self::PROJECTS_PER_PAGE,
        );

        $response = $this->render('pages/main/projects.html.twig', [
            'projects' => $projects['items'],
            'total' => $projects['total'],
            'page' => $projects['page'],
            'pages' => $projects['pages'],
            'search' => $search,
            'remoteFilter' => $remoteFilter,
            'filterErrors' => $errors,
            'hasFilters' => $search !== '' || $remoteFilter !== '',
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        $project = $projectRepository->findOnePublicBySlug($slug);
        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $profile = $currentProfileProvider->getProfile();
        $isTeamMember = $this->canAccessTeam($project, $profile, $participantRepository);
        $canApply = $this->canAcceptApplications($project, $participantRepository);

        return $this->noStore($this->render('pages/main/projects/detail.html.twig', [
            'project' => [
                'title' => $project->getTitle(),
                'slug' => $project->getSlug(),
                'summary' => $project->getSummary(),
                'description' => $project->getDescription(),
                'startDate' => $project->getStartDate(),
                'endDate' => $project->getEndDate(),
                'capacity' => $project->getCapacity(),
                'remotePossible' => $project->isRemotePossible(),
                'organisation' => $project->getOwnerOrganisation()?->getName(),
            ],
            'isTeamMember' => $isTeamMember,
            'canApply' => $canApply,
        ]));
    }

    #[Route('/{slug}/apply', name: 'apply', methods: ['GET'])]
    public function apply(
        string $slug,
        ProjectRepository $projectRepository,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        $project = $projectRepository->findOnePublicBySlug($slug);
        if ($project === null || !$this->canAcceptApplications($project, $participantRepository)) {
            throw $this->createNotFoundException('Aanmelden voor dit project is niet mogelijk.');
        }

        return $this->noStore($this->render('pages/main/projects/apply.html.twig', [
            'slug' => $project->getSlug(),
        ]));
    }

    #[Route('/{slug}/chat', name: 'chat', methods: ['GET'])]
    public function chat(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        return $this->renderTeamPage('chat', $slug, $projectRepository, $currentProfileProvider, $participantRepository);
    }

    #[Route('/{slug}/participants', name: 'participants', methods: ['GET'])]
    public function participants(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        return $this->renderTeamPage('participants', $slug, $projectRepository, $currentProfileProvider, $participantRepository);
    }

    #[Route('/{slug}/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        return $this->renderTeamPage('reviews', $slug, $projectRepository, $currentProfileProvider, $participantRepository);
    }

    #[Route('/{slug}/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        return $this->renderTeamPage('tasks', $slug, $projectRepository, $currentProfileProvider, $participantRepository);
    }

    #[Route('/{slug}/tasks/{taskId}/enroll', name: 'tasks.enroll', methods: ['POST'])]
    public function enrollTask(
        string $slug,
        string $taskId,
        Request $request,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
        WorkPackageTaskEnrollmentHandler $workPackageTaskEnrollmentHandler,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('enroll-package-task-' . $taskId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ongeldige aanvraag. Probeer het opnieuw.');

            return $this->redirectToRoute('main.projects.workPackages', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        $project = $projectRepository->findOneVisibleBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $profile = $currentProfileProvider->getProfile();

        if ($profile === null) {
            $this->addFlash('danger', 'Je moet ingelogd zijn met een profiel om je in te schrijven.');
            return $this->redirectToRoute('main.projects.workPackages', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        if (!$this->canAccessTeam($project, $profile, $participantRepository)) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $result = $workPackageTaskEnrollmentHandler->handle($project, $taskId, $profile);

        $this->addFlash($result->isSuccessful() ? 'success' : 'warning', $result->message);

        return $this->redirectToRoute('main.projects.workPackages', ['slug' => $slug], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{slug}/updates', name: 'updates', methods: ['GET'])]
    public function updates(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response
    {
        return $this->renderTeamPage('updates', $slug, $projectRepository, $currentProfileProvider, $participantRepository);
    }

    #[Route('/{slug}/work-packages', name: 'workPackages', methods: ['GET'])]
    public function workPackages(
        string $slug,
        GetProjectWorkPackagesHandler $handler,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response {
        $project = $projectRepository->findOneVisibleBySlug($slug);
        $this->assertTeamAccess($project, $currentProfileProvider->getProfile(), $participantRepository);

        return $this->noStore($this->render('pages/main/projects/work-packages.html.twig', [
            'slug' => $project->getSlug(),
            'workPackages' => $handler->handle($project->getSlug()),
        ]));
    }

    private function renderTeamPage(
        string $page,
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectParticipantRepository $participantRepository,
    ): Response {
        $project = $projectRepository->findOneVisibleBySlug($slug);
        $this->assertTeamAccess($project, $currentProfileProvider->getProfile(), $participantRepository);

        return $this->noStore($this->render('pages/main/projects/' . $page . '.html.twig', [
            'slug' => $project->getSlug(),
        ]));
    }

    private function assertTeamAccess(
        ?Project $project,
        ?Profile $profile,
        ProjectParticipantRepository $participantRepository,
    ): void {
        if ($project === null || !$this->canAccessTeam($project, $profile, $participantRepository)) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }
    }

    private function canAccessTeam(
        Project $project,
        ?Profile $profile,
        ProjectParticipantRepository $participantRepository,
    ): bool {
        if ($profile === null || $profile->getDeletedAt() !== null) {
            return false;
        }

        $profileAccount = $profile->getAccount();
        $ownerAccount = $project->getOwnerAccount();
        $isOwner = $profileAccount !== null
            && $ownerAccount !== null
            && (string) $profileAccount->getId() === (string) $ownerAccount->getId();

        return $isOwner || $participantRepository->isActiveParticipant($project, $profile);
    }

    private function canAcceptApplications(
        Project $project,
        ProjectParticipantRepository $participantRepository,
    ): bool {
        $status = strtolower((string) $project->getStatus()?->getName());
        if (!in_array($status, ['active', 'published'], true) || $project->getPublishedAt() === null) {
            return false;
        }

        $endDate = $project->getEndDate();
        if ($endDate !== null && $endDate < new \DateTimeImmutable('today')) {
            return false;
        }

        $capacity = $project->getCapacity();
        return $capacity === null || $participantRepository->countActiveForProject($project) < $capacity;
    }

    private function noStore(Response $response): Response
    {
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
