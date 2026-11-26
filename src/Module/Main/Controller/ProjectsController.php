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
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.publishedAt IS NOT NULL')
            ->andWhere('p.visibility = :visibility')
            ->setParameter('visibility', 'public')
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('pages/main/projects.html.twig', ['projects' => $projects]);
    }

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(
        string $slug,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository,
            true
        );

        return $this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
            'project' => $project,
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
            return $this->render('pages/main/projects/apply.html.twig', [
                'slug' => $slug,
                'project' => $project,
                'form' => null,
                'blocked' => new ProjectApplyResult(
                    ProjectApplyStatus::NoProfile,
                    'Je hebt een profiel nodig om je aan te melden.'
                ),
            ]);
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

        return $this->render('pages/main/projects/apply.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'form' => $form,
            'blocked' => $projectApplyHandler->check($project, $profile),
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
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
        Request $request,
        ProjectRepository $projectRepository,
        \App\Module\Main\Service\ProjectParticipantsService $participants,
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $project = $this->getProjectAndCheckViewAccess($slug, $projectRepository);
        $viewer = $this->getUser();
        $response = $this->render('pages/main/projects/participants.html.twig', [
            'project' => $project,
            'participants' => $participants->load($project, $viewer instanceof \App\Entity\Account\Account ? $viewer : null, $request->query->getInt('page', 1)),
        ]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');
        return $response;
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
        WorkPackageRepository $workPackageRepository,
    ): Response {
        $project = $this->getProjectAndCheckViewAccess(
            $slug,
            $projectRepository
        );

        return $this->render('pages/main/projects/tasks.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'packageTasks' => array_merge([], ...array_map(
                static fn ($workPackage): array => $workPackage->getWorkPackageTasks()->toArray(),
                $workPackageRepository->findActiveByProject($project)
            )),
        ]);
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

        return $this->render('pages/main/projects/updates.html.twig', [
            'slug' => $slug,
            'project' => $project,
        ]);
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

        return $this->render('pages/main/projects/work-packages.html.twig', [
            'slug' => $slug,
            'project' => $project,
            'workPackages' => $workPackageRepository->findActiveByProject($project),
        ]);
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

        if ($allowPublicAccess && $project->getVisibility() === 'public' && $project->getPublishedAt() !== null) {
            return $project;
        }

        if (!$allowPublicAccess || $project->getVisibility() !== 'public' || $project->getPublishedAt() === null) {
            $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);
        }

        return $project;
    }
}
