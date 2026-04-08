<?php

namespace App\Module\Main\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects', name: 'main.projects.')]
final class ProjectsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/main/projects.html.twig');
    }

    #[Route('/{id}', name: 'detail', methods: ['GET'])]
    public function detail(string $id): Response
    {
        return $this->render('pages/main/projects/detail.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/apply', name: 'apply', methods: ['GET'])]
    public function apply(string $id): Response
    {
        return $this->render('pages/main/projects/apply.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/chat', name: 'chat', methods: ['GET'])]
    public function chat(string $id): Response
    {
        return $this->render('pages/main/projects/chat.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/participants', name: 'participants', methods: ['GET'])]
    public function participants(string $id): Response
    {
        return $this->render('pages/main/projects/participants.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(string $id): Response
    {
        return $this->render('pages/main/projects/reviews.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(string $id): Response
    {
        return $this->render('pages/main/projects/tasks.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/updates', name: 'updates', methods: ['GET'])]
    public function updates(string $id): Response
    {
        return $this->render('pages/main/projects/updates.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/{id}/work-packages', name: 'workPackages', methods: ['GET'])]
    public function workPackages(string $id): Response
    {
        return $this->render('pages/main/projects/work-packages.html.twig', [
            'id' => $id,
        ]);
    }
}
