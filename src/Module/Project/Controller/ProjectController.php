<?php

namespace App\Module\Project\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'project.')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('project/list/index.html.twig');
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('project/create/index.html.twig');
    }

    #[Route('/vacancies', name: 'vacancies', methods: ['GET'])]
    public function vacancies(): Response
    {
        return $this->render('project/vacancies/index.html.twig');
    }

    #[Route('/{id}', name: 'view', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function view(string $id): Response
    {
        return $this->render('project/view/index.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/modify', name: 'modify', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function modify(string $id): Response
    {
        return $this->render('project/modify/index.html.twig', [
            'id' => $id,
        ]);
    }
}
