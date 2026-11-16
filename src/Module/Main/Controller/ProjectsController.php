<?php

namespace App\Module\Main\Controller;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Project\Project;
use App\Entity\Project\ProjectApplication;
use App\Module\Main\Handler\GetProjectWorkPackagesHandler;
use App\Module\Main\Handler\WorkPackageTaskEnrollmentHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\PackageTaskRepository;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'main.projects.')]
final class ProjectsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository): Response
    {
        $search = trim($request->query->getString('q'));
        $onlyAcceptingApplications = $request->query->getBoolean('open');
        $perPage = 20;
        $totalProjects = $projectRepository->countPublicProjects(
            $search,
            $onlyAcceptingApplications,
        );
        $totalPages = max(1, (int) ceil($totalProjects / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $totalPages);

        return $this->render('pages/main/projects.html.twig', [
            'publicProjects' => $projectRepository->findPaginatedPublicProjects(
                $search,
                $onlyAcceptingApplications,
                $page,
                $perPage,
            ),
            'search' => $search,
            'onlyAcceptingApplications' => $onlyAcceptingApplications,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProjects' => $totalProjects,
        ]);
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(
        string $slug,
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository,
            true,
        );

        $profile = $this->getSessionProfile($request, $entityManager);
        $application = null;
        if ($profile !== null) {
            foreach ($project->getApplications() as $projectApplication) {
                if ($projectApplication->getProfile()?->getId()->equals($profile->getId())
                    && $projectApplication->getDeletedAt() === null
                ) {
                    $application = $projectApplication;
                    break;
                }
            }
        }

        $statusName = strtolower(trim((string) $project->getStatus()?->getName()));

        return $this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'projectLeaderName' => trim((string) $project->getOwnerAccount()?->getProfile()?->getDisplayName()),
            'isOpen' => $statusName === 'open',
            'application' => $application,
            'previewMode' => false,
        ]);
    }

    #[Route('/{slug}/preview', name: 'preview', methods: ['GET'])]
    public function preview(
        string $slug,
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $accountId = $request->getSession()->get('account_id');
        if (!is_string($accountId) || $accountId === '') {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        if (!$account instanceof Account || $account->getDeletedAt() !== null || $account->getAdmin() === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $project = $projectRepository->findOneUnpublishedBySlug($slug);
        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        return $this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'projectLeaderName' => trim((string) $project->getOwnerAccount()?->getProfile()?->getDisplayName()),
            'isOpen' => false,
            'application' => null,
            'previewMode' => true,
        ]);
    }

    #[Route('/{slug}/apply', name: 'apply', methods: ['GET'])]
    public function apply(string $slug, ProjectRepository $projectRepository): Response
    {
        $project = $projectRepository->findOnePublicBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if (strtolower(trim((string) $project->getStatus()?->getName())) !== 'open') {
            $this->addFlash('warning', 'Dit project accepteert op dit moment geen aanmeldingen.');

            return $this->redirectToRoute('main.projects.detail', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/main/projects/apply.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/apply', name: 'submitApplication', methods: ['POST'])]
    public function submitApplication(
        string $slug,
        Request $request,
        ProjectRepository $projectRepository,
        StatusRepository $statusRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $project = $projectRepository->findOnePublicBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $accountId = $request->getSession()->get('account_id');
        if (!is_string($accountId) || $accountId === '') {
            $this->addFlash('error', 'Log in met een profiel om je aan te melden.');

            return $this->redirectToRoute('auth.login', [], Response::HTTP_SEE_OTHER);
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);
        $profile = $account instanceof Account
            ? $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account])
            : null;

        if (!$profile instanceof Profile) {
            $this->addFlash('error', 'Er is geen profiel gekoppeld aan je account.');

            return $this->redirectToRoute('main.projects.detail', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('project-apply-' . $project->getId()->toRfc4122(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Je aanmelding kon niet worden gecontroleerd. Probeer het opnieuw.');

            return $this->redirectToRoute('main.projects.detail', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        if (strtolower(trim((string) $project->getStatus()?->getName())) !== 'open') {
            $this->addFlash('warning', 'Dit project accepteert op dit moment geen aanmeldingen.');

            return $this->redirectToRoute('main.projects.detail', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        $application = null;
        foreach ($project->getApplications() as $existingApplication) {
            if ($existingApplication->getProfile()?->getId()->equals($profile->getId())) {
                if ($existingApplication->getDeletedAt() === null) {
                    $this->addFlash('info', 'Je hebt je al aangemeld voor dit project.');

                    return $this->redirectToRoute('account.applications', [], Response::HTTP_SEE_OTHER);
                }

                $application = $existingApplication;
                break;
            }
        }

        $pendingStatus = $statusRepository->findOneByScopeAndName('application', 'In behandeling');
        if ($pendingStatus === null) {
            $this->addFlash('error', 'Aanmelden is tijdelijk niet mogelijk omdat de aanvraagstatus niet is ingesteld.');

            return $this->redirectToRoute('main.projects.detail', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        $application ??= new ProjectApplication();
        $application->restore();
        $application
            ->setProject($project)
            ->setProfile($profile)
            ->setStatus($pendingStatus)
            ->setMotivation(trim((string) $request->request->get('motivation', '')));

        $entityManager->persist($application);
        $entityManager->flush();

        $this->addFlash('success', 'Je aanmelding is verstuurd. Je kunt de status bekijken bij Mijn aanmeldingen.');

        return $this->redirectToRoute('account.applications', [], Response::HTTP_SEE_OTHER);
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

        return $this->render('pages/main/projects/chat.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
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

        return $this->render('pages/main/projects/participants.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
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

        return $this->render('pages/main/projects/reviews.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->render('pages/main/projects/tasks.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/tasks/{taskId}/enroll', name: 'tasks.enroll', methods: ['POST'])]
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

        $project = $projectRepository->findOnePublicBySlug($slug);

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

        return $this->render('pages/main/projects/updates.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/work-packages', name: 'workPackages', methods: ['GET'])]
    public function workPackages(
        string $slug,
        GetProjectWorkPackagesHandler $handler,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository,
        );

        return $this->render('pages/main/projects/work-packages.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'workPackages' => $handler->handle($slug),
        ]);
    }

    private function getSessionProfile(Request $request, EntityManagerInterface $entityManager): ?Profile
    {
        $accountId = $request->getSession()->get('account_id');
        if (!is_string($accountId) || $accountId === '') {
            return null;
        }

        $account = $entityManager->getRepository(Account::class)->find($accountId);

        return $account instanceof Account
            ? $entityManager->getRepository(Profile::class)->findOneBy(['account' => $account])
            : null;
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
            !$allowPublicAccess
            || $project->getVisibility() === 'private'
        ) {
            $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        }

        return $project;
    }
}
