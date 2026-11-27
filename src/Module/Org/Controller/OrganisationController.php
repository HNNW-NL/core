<?php

namespace App\Module\Org\Controller;

use App\Entity\Project\Project;
use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;
use App\Security\Permission;

#[Route('/org', name: 'org.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class OrganisationController extends AbstractController
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
        if ($membership->getOrgRole()?->getOrganisation()?->getId()->toRfc4122() !== $id) {
        continue;
        }
     // Toon alleen organisaties waarvoor de gebruiker kijkrechten heeft.
      if (!$this->isGranted(Permission::ORG_VIEW->name, $organisatie)) {
      continue;
        }
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
                'magProjectenBeheren' => $this->isGranted(Permission::ORG_MANAGE_PROJECTS->name, $organisatie),
                'magMedewerkersBeheren' => $this->isGranted(Permission::ORG_MANAGE_MEMBERS->name, $organisatie),
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

    #[Route('/staff', name: 'staff', methods: ['GET'])]
    public function staff(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->getUser() instanceof Account) {
            throw $this->createAccessDeniedException('Log eerst in.');
        }

        $query = $request->query->all();
        $organisationId = $query['organisation_id']
            ?? $query['organisation']
            ?? null;

        if ($organisationId !== null) {
            if (!is_string($organisationId)) {
                throw $this->createNotFoundException('Organisatie niet gevonden.');
            }

            try {
                $id = Ulid::fromString($organisationId);
            } catch (\InvalidArgumentException) {
                throw $this->createNotFoundException('Organisatie niet gevonden.');
            }

            $organisation = $entityManager->find(Organisation::class, $id);

            if ($organisation === null || $organisation->getDeletedAt() !== null) {
                throw $this->createNotFoundException('Organisatie niet gevonden.');
            }

            $this->denyAccessUnlessGranted(
                Permission::ORG_MANAGE_MEMBERS->name,
                $organisation,
            );

            $request->query->set('organisation_id', $id->toBase32());
        }

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
