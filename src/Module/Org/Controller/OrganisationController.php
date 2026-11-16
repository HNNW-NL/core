<?php

namespace App\Module\Org\Controller;

use App\Entity\Project\Project;
use App\Repository\Project\ProjectRepository;
use App\Security\Voter\ProjectVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;
use App\Security\Permission;

#[Route('/org', name: 'org.')]
final class OrganisationController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser() === null) {
            throw $this->createAccessDeniedException('Log eerst in.');
        }
        return $this->render('pages/org/index.html.twig');
    }

    #[Route('/staff', name: 'staff', methods: ['GET'])]
    public function staff(): Response
    {
        if ($this->getUser() === null) {
            throw $this->createAccessDeniedException('Log eerst in.');
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
