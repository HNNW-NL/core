<?php

namespace App\Module\Org\Controller;

use App\Entity\Project\Project;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Admin\Handler\CreatePackageTaskHandler;
use App\Module\Admin\Handler\CreateWorkPackageHandler;
use App\Module\Admin\Handler\DeleteWorkPackageHandler;
use App\Module\Org\Handler\GetOrgProjectWorkPackagesHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

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
    public function inviteProjectParticipants(string $id): Response
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
        GetOrgProjectWorkPackagesHandler $handler,
        CreateWorkPackageHandler $createWorkPackageHandler,
        DeleteWorkPackageHandler $deleteWorkPackageHandler,
        CreatePackageTaskHandler $createPackageTaskHandler,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($request->isMethod('POST')) {

            $action = (string) $request->request->get('_action');

            if ($action === 'delete_work_package') {
                $deleteWorkPackageHandler->handle((string) $request->request->get('workPackageId'));

                return $this->redirectToRoute('org.modifyProject.workPackages', [
                    'id' => $id,
                ]);
            }

            if ($action === 'create_package_task') {
                $dueDateValue = $request->request->get('taskDueDate');

                $dto = new CreatePackageTaskDTO(
                    workPackageId: (string) $request->request->get('workPackageId'),
                    title: (string) $request->request->get('taskTitle'),
                    slug: (string) $request->request->get('taskSlug'),
                    description: $request->request->get('taskDescription') ?: null,
                    dueDate: $dueDateValue ? new \DateTimeImmutable($dueDateValue) : null,
                    priority: (string) ($request->request->get('taskPriority') ?: 'normal'),
                );

                $createPackageTaskHandler->handle($dto);

                return $this->redirectToRoute('org.modifyProject.workPackages', [
                    'id' => $id,
                ]);
            }

            if ($action === 'create_work_package') {
                $dueDateValue = $request->request->get('dueDate');

                $dto = new CreateWorkPackageDTO(
                    projectId: $id,
                    title: (string) $request->request->get('title'),
                    slug: (string) $request->request->get('slug'),
                    description: $request->request->get('description') ?: null,
                    dueDate: $dueDateValue ? new \DateTimeImmutable($dueDateValue) : null,
                );

                $createWorkPackageHandler->handle($dto);

                return $this->redirectToRoute('org.modifyProject.workPackages', [
                    'id' => $id,
                ]);
            }
        }

        $overview = $handler->handle($id);
        $project = Uuid::isValid($id)
            ? $entityManager->find(Project::class, Uuid::fromString($id))
            : null;

        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            'id' => $id,
            'projectTitle' => $project?->getTitle(),
            'projectMemberCount' => 0,
            'workPackages' => $overview['workPackages'],
            'workPackageCount' => $overview['workPackageCount'],
            'taskCount' => $overview['taskCount'],
            'averageProgress' => $overview['averageProgress'],
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
