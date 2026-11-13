<?php

namespace App\Module\Org\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Account\Account;
use Symfony\Component\HttpFoundation\Request;

#[Route('/org', name: 'org.')]
final class PanelController extends AbstractController
{

#[Route('', name: 'home', methods: ['GET'])]
public function index(Request $request): Response
{

    $account = $this->getUser();


    if (!$account instanceof Account) {
        throw $this->createAccessDeniedException(
            'Je moet ingelogd zijn.'
        );
    }




    $organisaties = [];

    foreach ($account->getOrgMemberships() as $membership) {
        $organisatie = $membership->getOrganisation();


        if ($organisatie === null ||
            $organisatie->getDeletedAt() !== null) {
            continue;
        }

        $id = $organisatie->getId()->toRfc4122();


        if (!isset($organisaties[$id])) {
       $aantalNietBeoordeeld = 0;

       foreach ($organisatie->getOwnedProjects() as $project) {
        if ($project->getDeletedAt() !== null) {
         continue;
       }

       foreach ($project->getApplications() as $aanvraag) {
        if ($aanvraag->getReviewedAt() === null &&
            $aanvraag->getDeletedAt() === null) {
            $aantalNietBeoordeeld++;
        }
    }
}
            $organisaties[$id] = [
                'id' => $id,
                'naam' => $organisatie->getName(),
                'aantalProjecten' => $organisatie->getOwnedProjects()
                ->filter(fn ($project) => $project->getDeletedAt() === null)
                ->count(),
                'aantalNietBeoordeeld' => $aantalNietBeoordeeld,
                'rollen' => [],
            ];
        }


        $rol = $membership->getOrgRole();

        if ($rol !== null &&
            $rol->getOrganisation()?->getId()->toRfc4122() === $id &&
            $rol->getName() !== null) {
            $organisaties[$id]['rollen'][$rol->getName()] =
                $rol->getName();
        }
    }

    // Lees de gekozen organisatie uit de URL
    $gekozenId = $request->query->get('organisation');

    // Alleen een organisatie kiezen waarvan je lid bent
    if ($gekozenId !== null && !isset($organisaties[$gekozenId])) {
        throw $this->createNotFoundException(
            'Organisatie niet gevonden.'
        );
    }

    // Kies de geselecteerde of eerste organisatie
    $actieveOrganisatie = $gekozenId !== null
        ? $organisaties[$gekozenId]
        : (reset($organisaties) ?: null);

    return $this->render('pages/org/index.html.twig', [
        'organisaties' => array_values($organisaties),
        'actieveOrganisatie' => $actieveOrganisatie,
    ]);
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
