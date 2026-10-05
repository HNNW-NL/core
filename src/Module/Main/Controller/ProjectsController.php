<?php

namespace App\Module\Main\Controller;

use App\Form\Main\ProjectApplyType;
use App\Module\Main\DTO\ProjectApplyDto;
use App\Module\Main\DTO\ProjectApplyResult;
use App\Module\Main\DTO\ProjectApplyStatus;
use App\Module\Main\Handler\ProjectApplyHandler;
use App\Module\Main\Handler\WorkPackageTaskEnrollmentHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Module\Main\Handler\GetProjectWorkPackagesHandler;

#[Route('/projects', name: 'main.projects.')]
final class ProjectsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/main/projects.html.twig');
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(string $slug): Response
    {
        return $this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
        ]);
    } 

    #[Route('/{slug}/apply', name: 'apply', methods: ['GET', 'POST'])]
    public function apply(
        string $slug,
        Request $request,
        ProjectRepository $projectRepository,
        CurrentProfileProvider $currentProfileProvider,
        ProjectApplyHandler $projectApplyHandler,
    ): Response {
        $project = $projectRepository->findOneVisibleBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if ($this->getUser() === null) {
            $this->addFlash('error', 'Je moet ingelogd zijn om je aan te melden voor een project.');

            return $this->redirectToRoute('auth.login', [], Response::HTTP_SEE_OTHER);
        }

        $profile = $currentProfileProvider->getProfile();

        // ingelogd maar nog geen profiel, dan laat de pagina dat zien in plaats van het formulier
        if ($profile === null) {
            return $this->render('pages/main/projects/apply.html.twig', [
                'slug' => $slug,
                'project' => $project,
                'form' => null,
                'blocked' => new ProjectApplyResult(ProjectApplyStatus::NoProfile, 'Je hebt een profiel nodig om je aan te melden.'),
            ]);
        }

        $dto = new ProjectApplyDto();
        $form = $this->createForm(ProjectApplyType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $result = $projectApplyHandler->handle($project, $profile, $dto);

            // terug naar deze pagina, daar zie je de melding en staat dat je al aangemeld bent
            $this->addFlash($result->isSuccessful() ? 'success' : 'warning', $result->message);

            return $this->redirectToRoute('main.projects.apply', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }

        return $this->render('pages/main/projects/apply.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'form' => $form,
            'blocked' => $projectApplyHandler->check($project, $profile),
        ]);
    }

    #[Route('/{slug}/chat', name: 'chat', methods: ['GET'])]
    public function chat(string $slug): Response
    {
        return $this->render('pages/main/projects/chat.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/participants', name: 'participants', methods: ['GET'])]
    public function participants(string $slug): Response
    {
        return $this->render('pages/main/projects/participants.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(string $slug): Response
    {
        return $this->render('pages/main/projects/reviews.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(string $slug): Response
    {
        return $this->render('pages/main/projects/tasks.html.twig', [
            'slug' => $slug,
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

        $result = $workPackageTaskEnrollmentHandler->handle($project, $taskId, $profile);

        $this->addFlash($result->isSuccessful() ? 'success' : 'warning', $result->message);

        return $this->redirectToRoute('main.projects.workPackages', ['slug' => $slug], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{slug}/updates', name: 'updates', methods: ['GET'])]
    public function updates(string $slug): Response
    {
        return $this->render('pages/main/projects/updates.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/work-packages', name: 'workPackages', methods: ['GET'])]
    public function workPackages( 
         string $slug,
         GetProjectWorkPackagesHandler $handler,
  ): Response {
      return $this->render('pages/main/projects/work-packages.html.twig', [
          'slug' => $slug,
          'workPackages' => $handler->handle($slug),
    ]);
 }
}
