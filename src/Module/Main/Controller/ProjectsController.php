<?php

namespace App\Module\Main\Controller;

use App\Entity\Project\Project;
use App\Module\Main\Handler\WorkPackageTaskEnrollmentHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectRepository;
use App\Repository\Project\WorkPackageRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\Main\ProjectApplyType;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Module\Main\Handler\ProjectApplyHandler;

#[Route('/projects', name: 'main.projects.')]
final class ProjectsController extends AbstractController
{
    private const PROJECTS_PER_PAGE = 20;

    #[Route('', name: 'home', methods: ['GET'])]
    public function index(
        Request $request,
        ProjectRepository $projectRepository,
    ): Response {
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

        $rawOpenFilter = $query['open'] ?? '';
        if (!is_string($rawOpenFilter) || !in_array($rawOpenFilter, ['', '1', '0'], true)) {
            $errors[] = 'De gekozen aanmeldfilter is ongeldig.';
            $rawOpenFilter = '';
        }
        $onlyAcceptingApplications = $rawOpenFilter === '1';

        $rawPage = $query['page'] ?? '1';
        $requestedPage = is_string($rawPage)
            ? filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($requestedPage === false) {
            $errors[] = 'Het paginanummer is ongeldig. Je ziet pagina 1.';
            $requestedPage = 1;
        }

        $remotePossible = $remoteFilter === 'yes' ? true : ($remoteFilter === 'no' ? false : null);

        $totalProjects = $projectRepository->countPublicProjects(
            $search,
            $onlyAcceptingApplications,
            $remotePossible,
        );

        $totalPages = max(1, (int) ceil($totalProjects / self::PROJECTS_PER_PAGE));
        $page = min(max(1, $requestedPage), $totalPages);

        $response = $this->render('pages/main/projects.html.twig', [
            'publicProjects' => $projectRepository->findPaginatedPublicProjects(
                $search,
                $onlyAcceptingApplications,
                $page,
                self::PROJECTS_PER_PAGE,
                $remotePossible,
            ),
            'search' => $search,
            'onlyAcceptingApplications' => $onlyAcceptingApplications,
            'remoteFilter' => $remoteFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProjects' => $totalProjects,
            'filterErrors' => $errors,
            'hasFilters' => $search !== '' || $onlyAcceptingApplications || $remoteFilter !== '',
        ]);

        return $this->noStore($response);
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(
        string $slug,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        \App\Module\Main\Service\ProjectApplyService $projectApplyService,
        \App\Module\Main\Service\ProjectLeaderDisplayService $projectLeaderDisplayService,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository,
            true
        );

        $profile = $this->getUser() !== null
            ? $currentProfileProvider->getProfile()
            : null;

        $blocked = $profile !== null
            ? $projectApplyService->check($project, $profile)
            : null;

        return $this->noStore($this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'application' => $blocked?->application,
            'blocked' => $blocked,
            'isOpen' => $projectApplyService->isOpen($project),
            'projectLeaderDisplayName' => $projectLeaderDisplayService->getDisplayName($project),
        ]));
    }

    #[Route('/{slug}/preview', name: 'preview', methods: ['GET'])]
    public function preview(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $project = $projectRepository->findOneUnpublishedBySlug($slug);

        if (
            $project === null
            || $project->getOwnerOrganisation() === null
            || $project->getOwnerOrganisation()->getDeletedAt() !== null
        ) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        return $this->noStore($this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'previewMode' => true,
            'application' => null,
            'blocked' => null,
            'isOpen' => false,
        ]));
    }

    #[Route('/{slug}/apply', name: 'apply', methods: ['GET', 'POST'])]
    public function apply(
        string $slug,
        Request $request,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectApplyHandler $projectApplyHandler,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository,
            true
        );

        if ($this->getUser() === null) {
            $this->addFlash(
                'error',
                'Je moet ingelogd zijn om je aan te melden voor een project.'
            );

            return $this->redirectToRoute(
                'auth.login',
                [],
                Response::HTTP_SEE_OTHER
            );
        }

        $profile = $currentProfileProvider->getProfile();

        if ($profile === null) {
            return $this->noStore($this->render('pages/main/projects/apply.html.twig', [
                'slug' => $slug,
                'project' => $project,
                'form' => null,
                'blocked' => new ProjectApplyResult(
                    ProjectApplyStatus::NoProfile,
                    'Je hebt een profiel nodig om je aan te melden.'
                ),
            ]));
        }

        $dto = new ProjectApplyDto();
        $form = $this->createForm(ProjectApplyType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $projectApplyHandler->handle(
                $project,
                $profile,
                $dto
            );

            $this->addFlash(
                $result->isSuccessful() ? 'success' : 'warning',
                $result->message
            );

            return $this->redirectToRoute(
                'main.projects.apply',
                ['slug' => $slug],
                Response::HTTP_SEE_OTHER
            );
        }

        return $this->noStore($this->render('pages/main/projects/apply.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'form' => $form,
            'blocked' => $projectApplyHandler->check($project, $profile),
        ]));
    }

    #[Route('/{slug}/chat', name: 'chat', methods: ['GET'])]
    public function chat(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->noStore($this->render('pages/main/projects/chat.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]));
    }

    #[Route('/{slug}/participants', name: 'participants', methods: ['GET'])]
    public function participants(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->noStore($this->render('pages/main/projects/participants.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]));
    }

    #[Route('/{slug}/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->noStore($this->render('pages/main/projects/reviews.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]));
    }

    #[Route('/{slug}/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(
        string $slug,
        ProjectRepository $projectRepository,
        WorkPackageRepository $workPackageRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->noStore($this->render('pages/main/projects/tasks.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'packageTasks' => array_merge([], ...array_map(
                static fn ($workPackage): array => $workPackage->getWorkPackageTasks()->toArray(),
                $workPackageRepository->findActiveByProject($project)
            )),
        ]));
    }

    #[Route('/{slug}/tasks/{taskId}/enroll', name: 'workPackages.enrollTask', methods: ['POST'])]
    public function enrollTask(
        string $slug,
        string $taskId,
        Request $request,
        ProjectRepository $projectRepository,
        PackageTaskRepository $packageTaskRepository,
        CurrentProfileProvider $currentProfileProvider,
        WorkPackageTaskEnrollmentHandler $workPackageTaskEnrollmentHandler,
    ): RedirectResponse {
        if (
            !$this->isCsrfTokenValid(
                'enroll-package-task-' . $taskId,
                (string) $request->request->get('_token')
            )
        ) {
            $this->addFlash(
                'danger',
                'Ongeldige aanvraag. Probeer het opnieuw.'
            );

            return $this->redirectToRoute(
                'main.projects.workPackages',
                ['slug' => $slug],
                Response::HTTP_SEE_OTHER
            );
        }

        $project = $projectRepository->findOneVisibleBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        $packageTask = $packageTaskRepository->findOneForProject(
            $taskId,
            $project
        );

        if ($packageTask === null) {
            throw $this->createNotFoundException('Taak niet gevonden.');
        }

        $profile = $currentProfileProvider->getProfile();

        if ($profile === null) {
            $this->addFlash(
                'danger',
                'Je moet ingelogd zijn met een profiel om je in te schrijven.'
            );

            return $this->redirectToRoute(
                'main.projects.workPackages',
                ['slug' => $slug],
                Response::HTTP_SEE_OTHER
            );
        }

        $result = $workPackageTaskEnrollmentHandler->handle(
            $project,
            $taskId,
            $profile
        );

        $this->addFlash(
            $result->isSuccessful() ? 'success' : 'warning',
            $result->message
        );

        return $this->redirectToRoute(
            'main.projects.workPackages',
            ['slug' => $slug],
            Response::HTTP_SEE_OTHER
        );
    }

    #[Route('/{slug}/updates', name: 'updates', methods: ['GET'])]
    public function updates(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->noStore($this->render('pages/main/projects/updates.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]));
    }

    #[Route('/{slug}/work-packages', name: 'workPackages', methods: ['GET'])]
    public function workPackages(
        string $slug,
        WorkPackageRepository $workPackageRepository,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->noStore($this->render('pages/main/projects/work-packages.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'workPackages' => $workPackageRepository->findActiveByProject($project),
        ]));
    }

    /**
     * Haalt een project op en controleert direct of de huidige
     * gebruiker dit specifieke project mag bekijken.
     */
    private function getProjectAndCheckViewAccess(
        string $slug,
        ProjectRepository $projectRepository,
        bool $allowPublicAccess = false,
    ): Project {
        $project = $projectRepository->findOneVisibleBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if (
            $project->getOwnerOrganisation() === null
            || $project->getOwnerOrganisation()->getDeletedAt() !== null
        ) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if ($project->getPublishedAt() === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if (
            $allowPublicAccess
            && $project->getVisibility() === 'public'
            && $project->getPublishedAt() !== null
            && strtolower((string) $project->getStatus()?->getName()) !== 'archived'
        ) {
            return $project;
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $project;
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
