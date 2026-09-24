<?php

namespace App\Module\Main\Controller;

use App\Module\Main\Handler\GetProjectWorkPackagesHandler;
use App\Module\Main\Handler\WorkPackageTaskEnrollmentHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'main.projects.')]
final class ProjectsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/main/projects.html.twig');
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $projectRepository->findOneVisibleBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
    }

    #[Route('/{slug}/apply', name: 'apply', methods: ['GET'])]
    public function apply(string $slug): Response
    {
        return $this->render('pages/main/projects/apply.html.twig', [
            'slug' => $slug,
        ]);
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
            $projectRepository
        );

        return $this->render('pages/main/projects/work-packages.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'workPackages' => $handler->handle($slug),
        ]);
    }

    /**
     * Haalt een project op en controleert direct of de huidige
     * gebruiker dit specifieke project mag bekijken.
     */
    private function getProjectAndCheckViewAccess(
        string $slug,
        ProjectRepository $projectRepository,
    ): object {
        $project = $projectRepository->findOneVisibleBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $project;
    }
}