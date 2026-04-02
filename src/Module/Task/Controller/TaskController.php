<?php

namespace App\Module\Task\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks', name: 'task.')]
final class TaskController extends AbstractController
{
    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('task/create/index.html.twig');
    }

    #[Route('/{id}', name: 'view', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function view(string $id): Response
    {
        return $this->render('task/view/index.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/modify', name: 'modify', methods: ['GET'], requirements: ['id' => '[A-Za-z0-9\-]+'])]
    public function modify(string $id): Response
    {
        return $this->render('task/modify/index.html.twig', [
            'id' => $id,
        ]);
    }
}
