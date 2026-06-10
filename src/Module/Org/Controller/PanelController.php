<?php

namespace App\Module\Org\Controller;

use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/projects/modify/{id}/participants', name: 'modifyProject.participants', methods: ['GET'])]
    public function modifyProjectParticipants(string $id): Response
    {
        return $this->render('pages/org/projects/modify-participants.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/participants/invite', name: 'modifyProject.inviteParticipants', methods: ['GET'])]
    public function InviteProjectParticipants(string $id): Response
    {
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

    #[Route('/projects/modify/{id}/work-packages', name: 'modifyProject.workPackages', methods: ['GET', 'POST'])]
    public function modifyProjectWorkPackages(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($id);

        if (!$project) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');

            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

            $workPackage = new WorkPackage();
            $workPackage->setProject($project);
            $workPackage->setStatus($project->getStatus());
            $workPackage->setTitle($title);
            $workPackage->setSlug($slug . '-' . bin2hex(random_bytes(3)));
            $workPackage->setDescription($request->request->get('description'));

            $entityManager->persist($workPackage);
            $entityManager->flush();

            return $this->redirectToRoute('org.modifyProject.workPackages', [
                'id' => $id,
            ]);
        }

        $workPackages = $entityManager->getRepository(WorkPackage::class)->findBy(
            [
                'project' => $project,
                'deletedAt' => null,
            ],
            [
                'createdAt' => 'DESC',
            ]
        );

        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            'id' => $id,
            'project' => $project,
            'workPackages' => $workPackages,
        ]);
    }

    #[Route('/projects/modify/{projectId}/work-packages/{workPackageId}/delete', name: 'modifyProject.workPackages.delete', methods: ['POST'])]
    public function deleteWorkPackage(
        string $projectId,
        string $workPackageId,
        EntityManagerInterface $entityManager
    ): RedirectResponse {
        $workPackage = $entityManager->getRepository(WorkPackage::class)->find($workPackageId);

        if ($workPackage && (string) $workPackage->getProject()->getId() === $projectId) {
            $workPackage->softDelete();
            $entityManager->flush();
        }

        return $this->redirectToRoute('org.modifyProject.workPackages', [
            'id' => $projectId,
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