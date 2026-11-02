<?php

namespace App\Module\Org\Controller;

use App\Entity\Project\Project;
use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Common\Status;
use App\Form\Org\CreateProjectType;
use App\Form\Org\ModifyProjectType;
use App\Form\Org\PackageTaskType;
use App\Form\Org\ParticipantRolesType;
use App\Form\Org\WorkPackageType;
use App\Module\Org\DTO\CreateProjectDTO;
use App\Module\Org\DTO\ModifyProjectFormDTO;
use App\Module\Org\DTO\ModifyProjectDTO;
use App\Module\Org\DTO\PackageTaskFormDTO;
use App\Module\Org\DTO\ParticipantRolesDTO;
use App\Module\Org\DTO\WorkPackageFormDTO;
use App\Module\Org\Handler\GetOrgProjectWorkPackagesHandler;
use App\Module\Org\Service\ModifyProjectService;
use App\Module\Admin\DTO\CreateWorkPackageDTO;
use App\Module\Admin\DTO\CreatePackageTaskDTO;
use App\Module\Admin\Service\WorkPackageCrudService;
use App\Module\Admin\Service\PackageTaskCrudService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\Project\ProjectRepository;
use App\Repository\Project\ProjectParticipantRepository;
use App\Repository\Project\ProjectRoleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/projects/create', name: 'createProject', methods: ['GET', 'POST'])]
    public function createProject(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $account = $this->getUser();
        if (!$account instanceof Account) {
            throw $this->createAccessDeniedException();
        }
        $organisationId = $request->query->getString('organisation_id');
        $organisation = null;
        foreach ($account->getOrgMemberships() as $membership) {
            $candidate = $membership->getOrganisation();
            if ($candidate && ($organisationId === '' || (string) $candidate->getId() === $organisationId)) {
                $organisation = $candidate;
                break;
            }
        }
        if (!$organisation && $this->isGranted('ROLE_ADMIN') && Uuid::isValid($organisationId)) {
            $organisation = $em->find(Organisation::class, $organisationId);
        }
        if (!$organisation) {
            throw $this->createAccessDeniedException('No organisation membership found.');
        }
        $data = new CreateProjectDTO();
        $data->ownerAccount = $account;
        $data->ownerOrganisation = $organisation;
        $form = $this->createForm(CreateProjectType::class, $data);
        $form->add('capacity', IntegerType::class, [
            'required' => true,
            'constraints' => [new NotBlank(message: 'Vul de capaciteit in.'), new PositiveOrZero()],
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $status = $data->status ?? $em->getRepository(Status::class)->findOneBy(['scope' => 'project', 'name' => 'draft']);
            if (!$status) {
                $form->addError(new FormError('No project status is available.'));
                return $this->render('pages/org/projects/create.html.twig', ['form' => $form->createView()]);
            }
            $project = new Project();
            $project->setTitle($data->name);
            $project->setSummary($data->summary);
            $project->setDescription($data->description);
            $project->setCapacity($data->capacity);
            $project->setVisibility($data->visibility);
            $project->setSlug(strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $data->name), '-')) . '-' . $project->getId());
            $project->setStartDate(new \DateTimeImmutable());
            $project->setOwnerAccount($account);
            $project->setOwnerOrganisation($organisation);
            $project->setStatus($status);
            $em->persist($project);
            $em->flush();
            return $this->redirectToRoute('org.modifyProject', ['id' => (string) $project->getId()]);
        }

        return $this->render('pages/org/projects/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET', 'POST'])]
    public function modifyProject(string $id, ProjectRepository $projects, Request $request, ModifyProjectService $service): Response
    {
        $project = $this->findProject($id, $projects);
        $data = new ModifyProjectFormDTO();
        $data->projectId = (string) $project->getId();
        $data->organisationId = $project->getOwnerOrganisation() ? (string) $project->getOwnerOrganisation()->getId() : null;
        $data->lastModified = $project->getLastModified()?->format('Y-m-d H:i:s');
        $data->title = $project->getTitle();
        $data->summary = $project->getSummary();
        $data->description = $project->getDescription();
        $data->visibility = $project->getVisibility();
        $data->startDate = $project->getStartDate();
        $data->endDate = $project->getEndDate();
        $data->capacity = $project->getCapacity();
        $data->status = $project->getStatus() ? strtolower((string) $project->getStatus()->getName()) : null;
        $form = $this->createForm(ModifyProjectType::class, $data, [
            'csrf_token_id' => 'org_modify_project_' . $data->projectId,
        ]);
        $form->handleRequest($request);
        $intent = $request->request->getString('intent', 'modify');
        if ($request->isMethod('POST') && $intent === 'delete') {
            $submitted = $request->request->all($form->getName());
            if (!$this->isCsrfTokenValid('org_modify_project_' . $project->getId(), $submitted['_token'] ?? null)) {
                throw $this->createAccessDeniedException('Invalid form token.');
            }
            $service->delete((string) $project->getId(), (string) $project->getOwnerOrganisation()?->getId());
            return $this->redirectToRoute('org.projects');
        }
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->modify(new ModifyProjectDTO(
                    projectId: (string) $project->getId(),
                    organisationId: (string) $project->getOwnerOrganisation()?->getId(),
                    title: $data->title,
                    summary: $data->summary,
                    description: $data->description,
                    visibility: $data->visibility,
                    statusName: $data->status,
                    startDate: $data->startDate,
                    endDate: $data->endDate,
                    startDateTouched: true,
                    endDateTouched: true,
                    visibilityTouched: true,
                    capacity: $data->capacity,
                    capacityTouched: true,
                    expectedLastModified: $data->lastModified ? new \DateTimeImmutable($data->lastModified) : null,
                    modifiedBy: $this->getUser(),
                ));
                return $this->redirectToRoute('org.modifyProject', ['id' => $id, 'status' => 'success', 'message' => 'Changes saved.']);
            } catch (\InvalidArgumentException | \RuntimeException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('pages/org/projects/modify.html.twig', [
            'id' => $id,
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/projects/modify/{id}/matching', name: 'modifyProject.matching', methods: ['GET'])]
    public function modifyProjectMatching(string $id): Response
    {
        return $this->render('pages/org/projects/modify-matching.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/projects/modify/{id}/participants', name: 'modifyProject.participants', methods: ['GET', 'POST'])]
    public function modifyProjectParticipants(
        string $id,
        ProjectRepository $projects,
        ProjectParticipantRepository $participants,
        ProjectRoleRepository $roles,
        Request $request,
        EntityManagerInterface $em,
    ): Response
    {
        $project = $this->findProject($id, $projects);
        $data = new ParticipantRolesDTO();
        $rows = [];
        $choices = [];
        foreach ($roles->findByProjectIdRoles((string) $project->getId()) as $role) {
            $choices[(string) $role->getName()] = (string) $role->getId();
        }
        $projectParticipants = $participants->findBy(['project' => $project]);
        foreach ($projectParticipants as $participant) {
            $participantId = (string) $participant->getId();
            $data->role[$participantId] = $participant->getRole() ? (string) $participant->getRole()->getId() : null;
            $rows[] = [
                'participant_id' => $participantId,
                'display_name' => $participant->getProfile()?->getDisplayName(),
                'status_name' => $participant->getStatus()?->getName(),
                'joinedAt' => $participant->getJoinedAt(),
                'leftAt' => $participant->getLeftAt(),
            ];
        }
        $form = $this->createForm(ParticipantRolesType::class, $data, ['role_choices' => $choices]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($projectParticipants as $participant) {
                $roleId = $data->role[(string) $participant->getId()] ?? null;
                $participant->setRole($roleId ? $roles->find($roleId) : null);
            }
            $em->flush();
            return $this->redirectToRoute('org.modifyProject.participants', ['id' => $id]);
        }

        return $this->render('pages/org/projects/modify-participants.html.twig', [
            'id' => $id,
            'participants' => $rows,
            'form' => $form->createView(),
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
        ProjectRepository $projects,
        GetOrgProjectWorkPackagesHandler $workPackages,
        Request $request,
        WorkPackageCrudService $workPackageService,
        PackageTaskCrudService $taskService,
    ): Response
    {
        $project = $this->findProject($id, $projects);
        $overview = $workPackages->handle((string) $project->getId());
        $form = $this->createForm(WorkPackageType::class, new WorkPackageFormDTO());
        $action = $request->request->getString('_action');
        if ($request->isMethod('POST') && $action === 'delete_work_package') {
            $workPackageId = $request->request->getString('workPackageId');
            if (!in_array($workPackageId, array_column($overview['workPackages'], 'id'), true)) {
                throw $this->createNotFoundException('Work package not found in this project.');
            }
            if (!$this->isCsrfTokenValid('delete_work_package_' . $workPackageId, $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid form token.');
            }
            $workPackageService->delete($workPackageId);
            return $this->redirectToRoute('org.modifyProject.workPackages', ['id' => $id]);
        }
        if ($action === 'create_work_package') {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                try {
                    $workPackageService->create(new CreateWorkPackageDTO(
                        (string) $project->getId(), $data->title, $data->slug, $data->description, $data->dueDate,
                    ));
                    return $this->redirectToRoute('org.modifyProject.workPackages', ['id' => $id]);
                } catch (\InvalidArgumentException $exception) {
                    $form->addError(new FormError($exception->getMessage()));
                }
            }
        }
        $taskForms = [];
        foreach ($overview['workPackages'] as $workPackage) {
            $workPackageId = $workPackage['id'];
            // Unique names keep the task forms separate on the same page.
            $taskForm = $this->container->get('form.factory')->createNamed(
                'package_task_' . str_replace('-', '_', $workPackageId),
                PackageTaskType::class,
                new PackageTaskFormDTO($workPackageId),
            );
            if ($action === 'create_package_task') {
                $taskForm->handleRequest($request);
                if ($taskForm->isSubmitted() && $taskForm->isValid()) {
                    $data = $taskForm->getData();
                    // The package comes from the loaded project, never from the hidden field.
                    try {
                        $taskService->create(new CreatePackageTaskDTO(
                            $workPackageId, $data->taskTitle, $data->taskSlug, $data->taskDescription,
                            $data->taskDueDate, 'normal',
                        ));
                        return $this->redirectToRoute('org.modifyProject.workPackages', ['id' => $id]);
                    } catch (\InvalidArgumentException $exception) {
                        $taskForm->addError(new FormError($exception->getMessage()));
                    }
                }
            }
            $taskForms[$workPackageId] = $taskForm->createView();
        }

        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            ...$overview,
            'id' => $id,
            'projectTitle' => $project->getTitle(),
            'form' => $form->createView(),
            'taskForms' => $taskForms,
        ]);
    }

    private function findProject(string $id, ProjectRepository $projects): Project
    {
        $project = Uuid::isValid($id) ? $projects->find($id) : $projects->findOneBy(['slug' => $id]);
        if (!$project || $project->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Project not found.');
        }
        $this->denyAccessUnlessGranted('ROLE_USER');
        $account = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN') && (!$account instanceof Account || $project->getOwnerAccount()?->getId()->equals($account->getId()) !== true)) {
            throw $this->createAccessDeniedException('Only the project owner can manage this project.');
        }

        return $project;
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
