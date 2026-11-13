<?php

namespace App\Module\Org\Controller;

use App\Entity\Account\Skill;
use App\Entity\Project\Project;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET'])]
    public function modifyProject(
        string $id,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        return $this->render('pages/org/projects/modify.html.twig', [
            'id' => $id,
            'project' => $project,
            'availableSkills' => $entityManager->getRepository(Skill::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/projects/modify/{id}/skills', name: 'modifyProject.skills', methods: ['POST'])]
    public function updateProjectSkills(
        string $id,
        Request $request,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $project = $this->getProjectAndCheckEditAccess($id, $projectRepository);

        if (!$this->isCsrfTokenValid(
            'org_modify_project_skills_' . $project->getId(),
            (string) $request->request->get('_token', ''),
        )) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }

        $submittedIds = $request->request->all()['skills'] ?? [];
        if (!is_array($submittedIds)) {
            throw new BadRequestHttpException('Invalid skills selection.');
        }

        $skillIds = [];
        foreach ($submittedIds as $submittedId) {
            if (!is_string($submittedId) || !Uuid::isValid($submittedId)) {
                throw new BadRequestHttpException('Invalid skill selected.');
            }

            $skillIds[strtolower($submittedId)] = Uuid::fromString($submittedId);
        }
        $skillIds = array_values($skillIds);

        $skills = $skillIds === []
            ? []
            : $entityManager->getRepository(Skill::class)->findBy(['id' => $skillIds]);

        if (count($skills) !== count($skillIds)) {
            throw new BadRequestHttpException('One or more selected skills do not exist.');
        }

        $project->getSkills()->clear();
        foreach ($skills as $skill) {
            $project->addSkill($skill);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Project skills updated.');

        return $this->redirectToRoute('org.modifyProject', ['id' => $id], Response::HTTP_SEE_OTHER);
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
