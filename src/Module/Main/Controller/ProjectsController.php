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

    #[Route('/{slug}', name: 'detail', methods: ['GET'])]
    public function detail(string $slug): Response
    {
        return $this->render('pages/main/projects/detail.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/apply', name: 'apply', methods: ['GET'])]
    public function apply(string $slug): Response
    {
        return $this->render('pages/main/projects/apply.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/chat', name: 'chat', methods: ['GET'])]
    public function chat(string $slug): Response
    {
        return $this->render('pages/main/projects/chat.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/participants', name: 'participants', methods: ['GET'])]
    public function participants(string $slug): Response
    {
        return $this->render('pages/main/projects/participants.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(string $slug): Response
    {
        return $this->render('pages/main/projects/reviews.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/tasks', name: 'tasks', methods: ['GET'])]
    public function tasks(string $slug): Response
    {
        return $this->render('pages/main/projects/tasks.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/updates', name: 'updates', methods: ['GET'])]
    public function updates(string $slug): Response
    {
        return $this->render('pages/main/projects/updates.html.twig', [
            'slug' => $slug,
        ]);
    }

    #[Route('/{slug}/work-packages', name: 'workPackages', methods: ['GET'])]
    public function workPackages(string $slug): Response
    {
        return $this->render('pages/main/projects/work-packages.html.twig', [
            'slug' => $slug,
        ]);
    }
}
