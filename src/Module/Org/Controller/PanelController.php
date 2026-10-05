<?php

namespace App\Module\Org\Controller;

use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Project\Project;
use App\Form\Org\CreateProjectType;
use App\Module\Org\DTO\CreateProjectDTO;
use App\Module\Org\Service\CreateProjectService;
use App\Repository\Common\StatusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
}
