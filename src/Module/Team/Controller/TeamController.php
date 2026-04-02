<?php

namespace App\Module\Team\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/teams', name: 'team.')]
final class TeamController extends AbstractController
{
    #[Route('/{id}', name: 'view', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function view(string $id): Response
    {
        return $this->render('team/view/index.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/applications', name: 'applications', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function applications(string $id): Response
    {
        return $this->render('team/applications/index.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/roles', name: 'roles', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function roles(string $id): Response
    {
        return $this->render('team/roles/index.html.twig', [
            'id' => $id,
        ]);
    }
}
