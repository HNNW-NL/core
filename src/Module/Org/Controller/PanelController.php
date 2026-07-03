<?php

namespace App\Module\Org\Controller;

use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Module\Org\DTO\CreateProjectDTO;
use App\Module\Org\Service\CreateProjectService;
use App\Repository\Project\InviteParticipantRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

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

    #[Route('/projects/create', name: 'createProject', methods: ['GET', 'POST'])]
    public function createProject(Request $request, CreateProjectService $service): Response {
        if ($request->isMethod('POST')) {
            $dto = new CreateProjectDTO();

            $dto->name = $request->request->get('name');
            $dto->summary = $request->request->get('summary');
            $dto->description = $request->request->get('description');
            $dto->capacity = (int) $request->request->get('capacity');
            $dto->visibility = $request->request->get('visibility');

            $service->create($dto);

            return $this->redirectToRoute('org.projects');
        }

        return $this->render('pages/org/projects/create.html.twig');
    }

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET'])]
    public function modifyProject(string $id): Response
    {
        return $this->render('pages/org/projects/modify.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/matching', name: 'modifyProject.matching', methods: ['GET'])]
    public function modifyProjectMatching(string $id): Response
    {
        return $this->render('pages/org/projects/modify-matching.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/participants', name: 'modifyProject.participants')]
    public function modifyProjectParticipants(string $id, Request $request, ProjectParticipantRepository $participantRepository,
                                              ProjectRoleRepository $projectRoleRepository, ProjectParticipantUpdateRoleHandler $updateRoleHandler ): Response
    {
        if ($request->isMethod('POST')) {
            $updateRoleHandler->handle($request->request->all('role'));
        }

        $participants = $participantRepository
            ->findByProjectIdWithProfileAndStatus($id);

        $roles = $projectRoleRepository
            ->findByProjectIdRoles($id);

        return $this->render('pages/org/projects/modify-participants.html.twig', [
            'id' => $id,
            'participants' => $participants,
            'roles' => $roles,
        ]);
    }

    #[Route('/projects/modify/{id}/participants/invite', name: 'modifyProject.inviteParticipants', methods: ['GET', 'POST'])]
    public function InviteProjectParticipants(string $id, Request $request, InviteParticipantRepository $inviteParticipantRepository): Response
    {
        $query = $request->query->get('q');

        $participants = [];

        if ($request->isXmlHttpRequest() && $query) {
            $participants = $inviteParticipantRepository->searchByDisplayName($query, 20);

            return new JsonResponse(array_map(fn($p) => [
                'id' => $p['id'],
                'displayName' => $p['displayName'],
            ], $participants));
        }

        if ($request->isMethod('POST')) {

            $items = $request->request->all('items');

            foreach ($items as $item) {
                $profileId = $item['id'] ?? null;
                $displayName = $item['displayName'] ?? null;
            }
        }

        return $this->render('pages/org/projects/invite-participants.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/reviews', name: 'modifyProject.reviews', methods: ['GET'])]
    public function modifyProjectReviews(string $id): Response
    {
        return $this->render('pages/org/projects/modify-reviews.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/tasks', name: 'modifyProject.tasks', methods: ['GET'])]
    public function modifyProjectTasks(string $id): Response
    {
        return $this->render('pages/org/projects/modify-tasks.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/updates', name: 'modifyProject.updates', methods: ['GET'])]
    public function modifyProjectUpdates(string $id): Response
    {
        return $this->render('pages/org/projects/modify-updates.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/work-packages', name: 'modifyProject.workPackages', methods: ['GET'])]
    public function modifyProjectWorkPackages(string $id): Response
    {
        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            'id' => $id,
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
}
