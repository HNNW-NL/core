<?php

namespace App\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Project\Project;
use App\Module\Org\Handler\ModifyProjectHandler;
use App\Module\Org\Service\CreateProjectService;
use App\Module\Org\Service\ProjectLifecycle;
use App\Module\Org\Service\ProjectManagementAccess;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Module\Main\Handler\ChangeWorkPackageStatusHandler;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/org', name: 'org.')]
final class OrganisationProjectsController extends AbstractController
{
    public function __construct(private readonly ProjectManagementAccess $projectAccess) {}

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(): Response
    {
        if ($this->getUser() === null) {
            throw $this->createAccessDeniedException('Log eerst in.');
        }
        return $this->render('pages/org/projects.html.twig');
    }

    #[Route('/projects/create', name: 'createProject', methods: ['GET', 'POST'])]
    public function createProject(Request $request, CreateProjectService $service): Response
    {
        $account = $this->getUser();
        if (!$account instanceof Account || $account->getProfile() === null) {
            throw $this->createAccessDeniedException();
        }

        $organisations = $this->projectAccess->creationOrganisations();
        if ($organisations === []) {
            throw $this->createAccessDeniedException();
        }

        $organisationId = $request->isMethod('POST')
            ? $request->request->getString('organisation_id')
            : $request->query->getString('organisation', array_key_first($organisations));
        $organisation = $organisations[$organisationId] ?? null;
        if ($organisation === null) {
            throw $this->createAccessDeniedException('Select an organisation you can manage.');
        }

        $error = null;
        $input = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('org_create_project_' . $organisationId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid form token.');
            }
            $input = $request->request->all();
            try {
                $project = $service->create($organisation, $input);

                if ($this->projectAccess->canEdit($project)) {
                    return $this->redirectToRoute('org.modifyProject', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
                }

                return $this->redirectToRoute('org.projects', status: Response::HTTP_SEE_OTHER);
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('pages/org/projects/create.html.twig', [
            'organisation' => $organisation,
            'organisations' => $organisations,
            'leads' => $service->projectLeads($organisation),
            'error' => $error,
            'input' => array_filter($input, 'is_string'),
        ], new Response(status: $error === null ? 200 : 422));
    }

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET', 'POST'])]
    public function modifyProject(
        string $id,
        Request $request,
        ProjectRepository $projectRepository,
        ProjectLifecycle $lifecycle,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ModifyProjectHandler $handler,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('org_modify_project_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid form token.');
            }
            $intent = $request->request->getString('intent');
            $target = $request->request->getString('transition');
            try {
                if ($intent === 'modify' && $target === '') {
                    $fields = array_intersect_key($request->request->all(), array_flip([
                        'title', 'summary', 'description', 'visibility', 'startDate', 'endDate', 'capacity', 'last_modified',
                    ]));
                    $fields['project_id'] = (string) $project->getId();
                    $fields['organisation_id'] = (string) $project->getOwnerOrganisation()->getId();
                    $trustedRequest = $request->duplicate([], $fields, [
                        'id' => $id, 'organisation_id' => $fields['organisation_id'], '_current_project' => $project,
                    ]);
                    $result = $handler->handle($trustedRequest, $this->getUser());
                    if (($result['success'] ?? false) !== true || isset($result['error'])) {
                        throw new \InvalidArgumentException($result['error'] ?? 'The project could not be saved.');
                    }
                } elseif ($intent === 'delete') {
                    $entityManager->getConnection()->transactional(function () use ($entityManager, $project, $request): void {
                        $entityManager->refresh($project, LockMode::PESSIMISTIC_WRITE);
                        $this->denyProjectEditAccess($project);
                        if (!$this->projectAccess->canDelete($project)) {
                            throw $this->createAccessDeniedException();
                        }
                        if ($request->request->getString('delete_confirmation') !== 'DELETE') {
                            throw new \InvalidArgumentException('Type DELETE to confirm project deletion.');
                        }

                        $project->softDelete();
                        $project->unpublish();
                        $entityManager->flush();
                    });

                    return $this->redirectToRoute('org.projects', status: Response::HTTP_SEE_OTHER);
                } elseif ($intent === 'transition') {
                    $previous = $entityManager->getConnection()->transactional(function () use ($entityManager, $project, $lifecycle, $target): ?string {
                        $entityManager->refresh($project, LockMode::PESSIMISTIC_WRITE);
                        $this->denyProjectEditAccess($project);
                        $previous = $project->getStatus()?->getName();
                        $lifecycle->transition($project, $target);
                        $entityManager->flush();

                        return $previous;
                    });
                    $logger->info('Project status changed', [
                        'project_id' => $id, 'from' => $previous, 'to' => $target,
                        'account_id' => (string) $this->getUser()->getId(),
                    ]);
                } else {
                    throw new \InvalidArgumentException('Unknown project action.');
                }

                return $this->redirectToRoute('org.modifyProject', ['id' => $id], Response::HTTP_SEE_OTHER);
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('pages/org/projects/modify.html.twig', [
            'id' => $id,
            'project' => $project,
            'canDelete' => $this->projectAccess->canDelete($project),
            'transitions' => $lifecycle->allowedTargets($project),
            'error' => $error,
        ], new Response(status: $error === null ? 200 : 422));
    }

    #[Route('/projects/modify/{id}/matching', name: 'modifyProject.matching', methods: ['GET'])]
    public function modifyProjectMatching(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify-matching.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route('/projects/modify/{id}/participants', name: 'modifyProject.participants', methods: ['GET'])]
    public function modifyProjectParticipants(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify-participants.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route('/projects/modify/{id}/participants/invite', name: 'modifyProject.inviteParticipants', methods: ['GET'])]
    public function inviteProjectParticipants(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/invite-participants.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route('/projects/modify/{id}/reviews', name: 'modifyProject.reviews', methods: ['GET'])]
    public function modifyProjectReviews(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify-reviews.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route('/projects/modify/{id}/tasks', name: 'modifyProject.tasks', methods: ['GET'])]
    public function modifyProjectTasks(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify-tasks.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route('/projects/modify/{id}/updates', name: 'modifyProject.updates', methods: ['GET'])]
    public function modifyProjectUpdates(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify-updates.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route('/projects/modify/{id}/work-packages', name: 'modifyProject.workPackages', methods: ['GET'])]
    public function modifyProjectWorkPackages(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
    }

    #[Route(
        '/projects/modify/{id}/participants/applications/{applicationId}/accept',
        name: 'modifyProject.participants.acceptApplication',
        methods: ['POST'],
    )]
    public function acceptApplication(
        string $id,
        string $applicationId,
        Request $request,
        ProjectRepository $projectRepository,
        ProjectParticipantUpdateRoleHandler $applicationHandler,
        CurrentProfileProvider $currentProfileProvider,
    ): RedirectResponse {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        if (!$this->isCsrfTokenValid(self::reviewTokenId($applicationId), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');

            return $this->redirectToRoute('org.modifyProject.participants', ['id' => $id]);
        }

        $reviewer = $currentProfileProvider->getProfile();

        if ($reviewer === null) {
            throw $this->createAccessDeniedException('Geen profiel gevonden voor de ingelogde gebruiker.');
        }

        $result = $applicationHandler->acceptApplication($project, $applicationId, $reviewer);
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('org.modifyProject.participants', ['id' => $id]);
    }

    #[Route(
        '/projects/modify/{id}/participants/applications/{applicationId}/reject',
        name: 'modifyProject.participants.rejectApplication',
        methods: ['POST'],
    )]
    public function rejectApplication(
        string $id,
        string $applicationId,
        Request $request,
        ProjectRepository $projectRepository,
        ProjectParticipantUpdateRoleHandler $applicationHandler,
        CurrentProfileProvider $currentProfileProvider,
    ): RedirectResponse {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        if (!$this->isCsrfTokenValid(self::reviewTokenId($applicationId), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Ongeldig veiligheidstoken. Probeer het opnieuw.');

            return $this->redirectToRoute('org.modifyProject.participants', ['id' => $id]);
        }

        $reviewer = $currentProfileProvider->getProfile();

        if ($reviewer === null) {
            throw $this->createAccessDeniedException('Geen profiel gevonden voor de ingelogde gebruiker.');
        }

        $result = $applicationHandler->rejectApplication(
            $project,
            $applicationId,
            $request->request->getString('reason'),
            $reviewer,
        );
        $this->addFlash($result['success'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('org.modifyProject.participants', ['id' => $id]);
    }

    #[Route('/projects/modify/{id}/work-packages/{workPackageId}/status', name: 'modifyProject.workPackages.status', methods: ['POST'])]
    public function changeWorkPackageStatus(
        string $id,
        string $workPackageId,
        Request $request,
        ProjectRepository $projectRepository,
        ChangeWorkPackageStatusHandler $handler,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        if (!$this->isCsrfTokenValid('work_package_status_' . $workPackageId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Ongeldig veiligheidstoken.');
        }

        try {
            $handler->handle(new ChangeWorkPackageStatusDTO($workPackageId, $request->request->getString('status')), $project);
            $this->addFlash('success', 'De werkpakketstatus is gewijzigd.');
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'De statuswijziging is niet mogelijk. Controleer de huidige status en probeer opnieuw.');
        }

        return $this->redirectToRoute('org.modifyProject.workPackages', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    /** CSRF-token id per aanvraag; de template gebruikt exact dezelfde string. */
    private static function reviewTokenId(string $applicationId): string
    {
        return 'org_review_application_' . $applicationId;
    }

    private function getProjectAndCheckEditAccess(
        string $id,
        ProjectRepository $projectRepository,
    ): Project {
        if (!Ulid::isValid($id)) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $project = $projectRepository->findOneActiveById(Ulid::fromString($id));

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $this->denyProjectEditAccess($project);

        return $project;
    }

    private function denyProjectEditAccess(Project $project): void
    {
        if ($project->getDeletedAt() !== null || $project->getOwnerOrganisation()?->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }
        if (!$this->projectAccess->canEdit($project)) {
            throw $this->createAccessDeniedException();
        }
    }
}
