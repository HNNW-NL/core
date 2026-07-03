<?php

namespace App\Module\Org\Controller;

use App\Entity\Common\Status;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Repository\Project\WorkPackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/projects/create', name: 'createProject', methods: ['GET', 'POST'])]
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

    #[Route(
        '/projects/modify/{id}/work-packages',
        name: 'modifyProject.workPackages',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['GET', 'POST']
    )]
    public function modifyProjectWorkPackages(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        WorkPackageRepository $workPackageRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $project = $entityManager->getRepository(Project::class)->find($id);
            $status = $entityManager->getRepository(Status::class)->findOneBy([]);

            if ($project !== null && $status !== null) {
                $title = (string) $request->request->get('title', '');
                $description = (string) $request->request->get('description', '');

                $slug = strtolower(trim($title));
                $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
                $slug = trim((string) $slug, '-');

                $workPackage = new WorkPackage();
                $workPackage->setProject($project);
                $workPackage->setStatus($status);
                $workPackage->setTitle($title);
                $workPackage->setSlug($slug);
                $workPackage->setDescription($description);

                $entityManager->persist($workPackage);
                $entityManager->flush();
            }

            return $this->redirectToRoute('org.modifyProject.workPackages', [
                'id' => $id,
            ]);
        }

        $workPackages = $workPackageRepository->findActiveByProjectId($id);

        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            'id' => $id,
            'workPackages' => $workPackages,
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
