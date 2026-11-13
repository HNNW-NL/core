<?php

namespace App\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Project\Project;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Module\Org\Handler\ModifyProjectHandler;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Module\Org\Service\ModifyProjectService;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use App\Security\Permission;

#[Route('/org', name: 'org.')]
final class PanelController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/org/index.html.twig');
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(): Response
    {
        return $this->render('pages/org/projects.html.twig');
    }

    #[Route('/projects/create', name: 'createProject', methods: ['GET'])]
    public function createProject(): Response
    {
        return $this->render('pages/org/projects/create.html.twig');
    }

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET', 'POST'])]
    public function modifyProject(
        string $id,
        Request $request,
        ProjectRepository $projectRepository,
        ModifyProjectService $modifyProjectService,
        ModifyProjectHandler $modifyProjectHandler,
    ): Response {
        // Eerst bestaan + project- en organisatiebevoegdheid controleren; pas daarna mag de service iets doen.
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        $user = $this->getUser();

        $payload = $modifyProjectService->buildResponsePayload(
            (string) $project->getId(),
            $request,
            $modifyProjectHandler,
            $user instanceof Account ? $user : null,
        );

        // Na een actie (opslaan/verwijderen) terug naar de pagina met status + melding in de querystring (PRG).
        if (isset($payload['redirect'])) {
            return $this->redirectToRoute(
                $payload['redirectRoute'] ?? 'org.modifyProject',
                $payload['redirect'],
                Response::HTTP_SEE_OTHER,
            );
        }

        return $this->render('pages/org/projects/modify.html.twig', $payload['view']);
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
        ProjectParticipantRepository $participantRepository,
        ProjectParticipantUpdateRoleHandler $applicationHandler,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        $applications = $participantRepository->findApplicationsForProject($project);

        return $this->render('pages/org/projects/modify-participants.html.twig', [
            'id' => $id,
            'project' => $project,
            'applications' => $applications,
            'pending_application_ids' => array_map(
                static fn ($application): string => $application->getId()->toRfc4122(),
                array_filter($applications, $applicationHandler->isPending(...)),
            ),
            'reject_reason_max_length' => ProjectParticipantUpdateRoleHandler::REJECT_REASON_MAX_LENGTH,
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

    #[Route('/staff', name: 'staff', methods: ['GET'])]
    public function staff(): Response
    {
        return $this->render('pages/org/staff.html.twig');
    }

    #[Route('/staff/{id}', name: 'viewStaff', methods: ['GET'])]
    public function viewStaff(string $id): Response
    {
        return $this->render('pages/org/staff/view.html.twig', [
            'id' => $id,
        ]);
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
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $project = $projectRepository->findOneActiveById(Uuid::fromString($id));

        if ($project === null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $this->denyAccessUnlessGranted(Permission::ORG_MANAGE_PROJECTS->name, $project->getOwnerOrganisation());

        return $project;
    }
}