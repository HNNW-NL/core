<?php

namespace App\Module\Org\Controller;

use App\Entity\Project\Project;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Module\Main\Service\CurrentProfileProvider;
use App\Module\Main\Handler\ChangeWorkPackageStatusHandler;
use App\Module\Main\DTO\ChangeWorkPackageStatusDTO;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;
use App\Security\Permission;

#[Route('/org', name: 'org.')]
final class OrganisationProjectsController extends AbstractController
{
    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(): Response
    {
        if ($this->getUser() === null) {
            throw $this->createAccessDeniedException('Log eerst in.');
        }
        return $this->render('pages/org/projects.html.twig');
    }

    #[Route('/projects/create', name: 'createProject', methods: ['GET'])]
    public function createProject(): Response
    {
        return $this->render('pages/org/projects/create.html.twig');
    }

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET'])]
    public function modifyProject(
        string $id,
        ProjectRepository $projectRepository,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify.html.twig', [
            'id' => $id,
            'project' => $project,
        ]);
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

        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $this->denyAccessUnlessGranted(Permission::ORG_MANAGE_PROJECTS->name, $project->getOwnerOrganisation());

        return $project;
    }
}
