<?php

namespace App\Module\Main\Controller;

use App\Module\Main\Handler\WorkPackageTaskEnrollmentHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/projects', name: 'main.projects.')]
final class WorkPackageEnrollmentController extends AbstractController
{
    #[Route('/{slug}/work-packages/tasks/{taskId}/enroll', name: 'workPackages.enrollTask', methods: ['POST'])]
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

        if (!Uuid::isValid($taskId)) {
            $this->addFlash('danger', 'Ongeldige taak.');

            return $this->redirectToRoute('main.projects.workPackages', ['slug' => $slug], Response::HTTP_SEE_OTHER);
        }
        $result = $workPackageTaskEnrollmentHandler->handle($project, Uuid::fromString($taskId), $profile);

        $this->addFlash($result->isSuccessful() ? 'success' : 'warning', $result->message);

        return $this->redirectToRoute('main.projects.workPackages', ['slug' => $slug], Response::HTTP_SEE_OTHER);
    }
}