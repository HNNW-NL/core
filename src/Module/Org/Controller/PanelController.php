<?php

namespace App\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Project\PackageTask;
use App\Entity\Project\Project;
use App\Entity\Project\WorkPackage;
use App\Form\Org\CreateProjectType;
use App\Form\Org\ModifyProjectType;
use App\Form\Org\PackageTaskType;
use App\Form\Org\ParticipantRolesType;
use App\Form\Org\WorkPackageType;
use App\Module\Org\DTO\CreateProjectDTO;
use App\Module\Org\DTO\ModifyProjectFormDTO;
use App\Module\Org\DTO\PackageTaskFormDTO;
use App\Module\Org\DTO\ParticipantRolesDTO;
use App\Module\Org\DTO\WorkPackageFormDTO;
use App\Module\Org\Handler\GetOrgProjectWorkPackagesHandler;
use App\Module\Org\Handler\ProjectParticipantUpdateRoleHandler;
use App\Module\Org\Service\CreateProjectService;
use App\Repository\Common\StatusRepository;
use App\Repository\Project\ProjectRoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
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

    #[Route('/projects/create', name: 'createProject', methods: ['GET', 'POST'])]
    public function createProject(
        Request $request,
        EntityManagerInterface $entityManager,
        StatusRepository $statusRepository,
        CreateProjectService $createProjectService
    ): Response {
        $account = $this->getAuthenticatedAccount();

        $createProjectDTO = new CreateProjectDTO();

        $form = $this->createForm(CreateProjectType::class, $createProjectDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organisation = $this->findOrganisationForAccount($account);
            $slug = $createProjectService->makeSlug((string) $createProjectDTO->name);
            $projectWithSameSlug = $entityManager->getRepository(Project::class)->findOneBy(['slug' => $slug]);

            if ($organisation === null) {
                // zonder organisatie kan een project geen eigenaar hebben, dus dan stoppen we hier
                $form->addError(new FormError('Je bent geen lid van een organisatie, dus je kunt geen project aanmaken.'));
            } elseif ($projectWithSameSlug !== null) {
                // de slug moet uniek zijn in de database, anders geeft het opslaan een fout
                $form->get('name')->addError(new FormError('Er bestaat al een project met deze naam.'));
            } else {
                $createProjectDTO->ownerAccount = $account;
                $createProjectDTO->ownerOrganisation = $organisation;

                // als er geen status is gekozen pakken we de gewone project status uit de database
                if ($createProjectDTO->status === null) {
                    $createProjectDTO->status = $statusRepository->findOneByScopeAndName('project', 'active');
                }

                $project = $createProjectService->create($createProjectDTO);

                return $this->redirectToRoute('org.modifyProject', [
                    'id' => (string) $project->getId(),
                    'status' => 'success',
                    'message' => 'Project aangemaakt.',
                ]);
            }
        }

        return $this->render('pages/org/projects/create.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/projects/modify/{id}', name: 'modifyProject', methods: ['GET', 'POST'])]
    public function modifyProject(string $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = $this->findProjectForAccount($id, $entityManager);

        // de waarden van het project gaan eerst in de dto, het formulier werkt met de dto en niet met de entity
        $modifyProjectDTO = new ModifyProjectFormDTO();
        $modifyProjectDTO->projectId = (string) $project->getId();
        $modifyProjectDTO->organisationId = (string) $project->getOwnerOrganisation()?->getId();
        $modifyProjectDTO->lastModified = $project->getLastModified()->format('Y-m-d H:i:s');
        $modifyProjectDTO->title = $project->getTitle();
        $modifyProjectDTO->summary = $project->getSummary();
        $modifyProjectDTO->description = $project->getDescription();
        $modifyProjectDTO->visibility = $project->getVisibility();
        $modifyProjectDTO->startDate = $project->getStartDate();
        $modifyProjectDTO->endDate = $project->getEndDate();
        $modifyProjectDTO->capacity = $project->getCapacity();
        $modifyProjectDTO->status = $project->getStatus();

        $form = $this->createForm(ModifyProjectType::class, $modifyProjectDTO);
        $form->handleRequest($request);

        // de regels in het formulier kijken per veld, voor twee velden samen doen we het hier zelf: de pagina belooft dat de einddatum op of na de startdatum ligt
        $startDate = $modifyProjectDTO->startDate;
        $endDate = $modifyProjectDTO->endDate;
        if ($form->isSubmitted() && $startDate !== null && $endDate !== null && $endDate < $startDate) {
            $form->get('endDate')->addError(new FormError('De einddatum moet op of na de startdatum liggen.'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // de knop delete zit in hetzelfde formulier, de waarde van intent zegt wat de gebruiker wil
            if ($request->request->get('intent') === 'delete') {
                $project->softDelete();
                $entityManager->flush();

                return $this->redirectToRoute('org.projects');
            }

            // als het verborgen veld niet meer klopt heeft iemand anders het project ondertussen opgeslagen
            if ($modifyProjectDTO->lastModified !== $project->getLastModified()->format('Y-m-d H:i:s')) {
                return $this->redirectToRoute('org.modifyProject', [
                    'id' => $id,
                    'status' => 'error',
                    'message' => 'Het project is ondertussen door iemand anders aangepast. Laad de pagina opnieuw.',
                ]);
            }

            $project->setTitle($modifyProjectDTO->title);
            $project->setSummary($modifyProjectDTO->summary);
            $project->setDescription($modifyProjectDTO->description);
            $project->setVisibility($modifyProjectDTO->visibility);
            $project->setStartDate($modifyProjectDTO->startDate);
            $project->setEndDate($modifyProjectDTO->endDate);
            // de kolom capacity mag niet leeg zijn, dus zonder invulling slaan we 0 op
            $project->setCapacity($modifyProjectDTO->capacity ?? 0);

            if ($modifyProjectDTO->status !== null) {
                $project->setStatus($modifyProjectDTO->status);
            }

            $entityManager->flush();

            return $this->redirectToRoute('org.modifyProject', [
                'id' => $id,
                'status' => 'success',
                'message' => 'Project opgeslagen.',
            ]);
        }

        return $this->render('pages/org/projects/modify.html.twig', [
            'id' => $id,
            'project' => $project,
            'form' => $form,
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
        Request $request,
        EntityManagerInterface $entityManager,
        ProjectRoleRepository $projectRoleRepository,
        ProjectParticipantUpdateRoleHandler $updateRoleHandler
    ): Response {
        $project = $this->findProjectForAccount($id, $entityManager);

        // de keuzelijst voor het formulier, de naam van de rol is het label en het id is de waarde
        $roleChoices = [];
        foreach ($projectRoleRepository->findByProjectIdRoles((string) $project->getId()) as $role) {
            $roleChoices[$role->getName()] = (string) $role->getId();
        }

        // per deelnemer de huidige rol in de dto en de gegevens voor de tabel in de pagina
        $participantRolesDTO = new ParticipantRolesDTO();
        $participants = [];
        foreach ($project->getParticipants() as $participant) {
            $participantId = (string) $participant->getId();
            $profile = $participant->getProfile();

            $displayName = $profile?->getDisplayName();
            if ($displayName === null || $displayName === '') {
                $displayName = trim($profile?->getFirstName() . ' ' . $profile?->getLastName());
            }

            $participantRolesDTO->role[$participantId] = (string) $participant->getRole()?->getId();
            $participants[] = [
                'participant_id' => $participantId,
                'display_name' => $displayName,
                'status_name' => $participant->getStatus()?->getName(),
                'joinedAt' => $participant->getJoinedAt(),
                'leftAt' => $participant->getLeftAt(),
            ];
        }

        $form = $this->createForm(ParticipantRolesType::class, $participantRolesDTO, [
            'role_choices' => $roleChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateRoleHandler->handle($participantRolesDTO->role);

            return $this->redirectToRoute('org.modifyProject.participants', [
                'id' => $id,
            ]);
        }

        return $this->render('pages/org/projects/modify-participants.html.twig', [
            'id' => $id,
            'participants' => $participants,
            'form' => $form,
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
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
        StatusRepository $statusRepository,
        GetOrgProjectWorkPackagesHandler $overviewHandler
    ): Response {
        $project = $this->findProjectForAccount($id, $entityManager);

        // de verwijderknop is een gewoon html formulier en geen symfony form, daarom controleren we het csrf token hier zelf en zoeken we het werkpakket alleen binnen dit project
        if ($request->request->get('_action') === 'delete_work_package') {
            $workPackageId = (string) $request->request->get('workPackageId');
            $token = (string) $request->request->get('_token');

            if ($this->isCsrfTokenValid('delete_work_package', $token) && Uuid::isValid($workPackageId)) {
                $workPackage = $entityManager->getRepository(WorkPackage::class)->findOneBy([
                    'id' => $workPackageId,
                    'project' => $project,
                ]);

                if ($workPackage !== null) {
                    $workPackage->softDelete();
                    $entityManager->flush();
                }
            }

            return $this->redirectToRoute('org.modifyProject.workPackages', [
                'id' => $id,
            ]);
        }

        // formulier voor een nieuw werkpakket
        $workPackageDTO = new WorkPackageFormDTO();
        $form = $this->createForm(WorkPackageType::class, $workPackageDTO);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $status = $statusRepository->findOneByScopeAndName('WorkPackage', 'active');
            $workPackageWithSameSlug = $entityManager->getRepository(WorkPackage::class)->findOneBy([
                'project' => $project,
                'slug' => $workPackageDTO->slug,
            ]);

            if ($status === null) {
                $form->addError(new FormError('Er is geen status voor werkpakketten in de database.'));
            } elseif ($workPackageWithSameSlug !== null) {
                // de slug moet uniek zijn binnen het project, anders geeft het opslaan een fout
                $form->get('slug')->addError(new FormError('Er bestaat al een werkpakket met deze slug in dit project.'));
            } else {
                $workPackage = new WorkPackage();
                $workPackage->setProject($project);
                $workPackage->setStatus($status);
                $workPackage->setTitle($workPackageDTO->title);
                $workPackage->setSlug($workPackageDTO->slug);
                $workPackage->setDescription($workPackageDTO->description);
                $workPackage->setDueDate($workPackageDTO->dueDate);

                $entityManager->persist($workPackage);
                $entityManager->flush();

                return $this->redirectToRoute('org.modifyProject.workPackages', [
                    'id' => $id,
                ]);
            }
        }

        $overview = $overviewHandler->handle((string) $project->getId());

        // per werkpakket een eigen taakformulier met een eigen naam, zo weet symfony welk formulier is verstuurd
        $taskForms = [];
        foreach ($overview['workPackages'] as $workPackageRow) {
            $workPackageId = $workPackageRow['id'];

            $packageTaskDTO = new PackageTaskFormDTO($workPackageId);
            $taskForm = $formFactory->createNamed('package_task_' . $workPackageId, PackageTaskType::class, $packageTaskDTO);
            $taskForm->handleRequest($request);

            if ($taskForm->isSubmitted() && $taskForm->isValid()) {
                $workPackage = $entityManager->getRepository(WorkPackage::class)->find($workPackageId);
                $status = $statusRepository->findOneByScopeAndName('PackageTask', 'active');
                $taskWithSameSlug = $entityManager->getRepository(PackageTask::class)->findOneBy([
                    'workPackage' => $workPackage,
                    'slug' => $packageTaskDTO->taskSlug,
                ]);

                if ($status === null) {
                    $taskForm->addError(new FormError('Er is geen status voor taken in de database.'));
                } elseif ($taskWithSameSlug !== null) {
                    $taskForm->get('taskSlug')->addError(new FormError('Er bestaat al een taak met deze slug in dit werkpakket.'));
                } else {
                    $packageTask = new PackageTask();
                    $packageTask->setWorkPackage($workPackage);
                    $packageTask->setStatus($status);
                    $packageTask->setTitle($packageTaskDTO->taskTitle);
                    $packageTask->setSlug($packageTaskDTO->taskSlug);
                    $packageTask->setDescription($packageTaskDTO->taskDescription);
                    $packageTask->setDueDate($packageTaskDTO->taskDueDate);
                    $packageTask->setPriority($packageTaskDTO->taskPriority ?? 'normal');

                    $entityManager->persist($packageTask);
                    $entityManager->flush();

                    return $this->redirectToRoute('org.modifyProject.workPackages', [
                        'id' => $id,
                    ]);
                }
            }

            // de pagina verwacht per werkpakket een form view, de lijst zit in een array dus die maken we hier zelf
            $taskForms[$workPackageId] = $taskForm->createView();
        }

        return $this->render('pages/org/projects/modify-work-packages.html.twig', [
            'id' => $id,
            'projectTitle' => $project->getTitle(),
            'projectMemberCount' => $project->getParticipants()->count(),
            'workPackages' => $overview['workPackages'],
            'workPackageCount' => $overview['workPackageCount'],
            'taskCount' => $overview['taskCount'],
            'averageProgress' => $overview['averageProgress'],
            'form' => $form,
            'taskForms' => $taskForms,
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

    // geeft het ingelogde account terug, als niemand is ingelogd stuurt symfony de bezoeker naar de loginpagina
    private function getAuthenticatedAccount(): Account
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        if (!$user instanceof Account) {
            throw $this->createAccessDeniedException('Je moet ingelogd zijn om deze pagina te bekijken.');
        }

        return $user;
    }

    // zoekt de organisatie waar het account lid van is, als het goed is is dat er maar een
    private function findOrganisationForAccount(Account $account): ?Organisation
    {
        $membership = $account->getOrgMemberships()->first();

        if ($membership === false) {
            return null;
        }

        return $membership->getOrganisation();
    }

    // kijkt of het account bij de organisatie hoort, een admin mag altijd
    private function isMemberOfOrganisation(Account $account, ?Organisation $organisation): bool
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if ($organisation === null) {
            return false;
        }

        foreach ($account->getOrgMemberships() as $membership) {
            if ((string) $membership->getOrganisation()?->getId() === (string) $organisation->getId()) {
                return true;
            }
        }

        return false;
    }

    // zoekt het project op en controleert of de ingelogde gebruiker er iets mee mag doen
    private function findProjectForAccount(string $id, EntityManagerInterface $entityManager): Project
    {
        $account = $this->getAuthenticatedAccount();

        // zonder deze controle geeft doctrine een fout als de id geen uuid is
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        $project = $entityManager->getRepository(Project::class)->find($id);

        if ($project === null || $project->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Project niet gevonden.');
        }

        if (!$this->isMemberOfOrganisation($account, $project->getOwnerOrganisation())) {
            throw $this->createAccessDeniedException('Je hoort niet bij de organisatie van dit project.');
        }

        return $project;
    }
}
