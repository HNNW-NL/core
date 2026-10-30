<?php

namespace App\Module\Main\Controller;

use App\Repository\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'main.')]
final class MainController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        return $this->render('pages/main/index.html.twig', [
            'openProjects' => $projectRepository->findOpenForHomepage(),
        ]);
    }

    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('pages/main/about.html.twig');
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('pages/main/contact.html.twig');
    }

    #[Route('/profile/{slug}', name: 'profile', methods: ['GET'])]
    public function viewProfile(string $slug): Response
    {
        return $this->render('pages/main/profile.html.twig', [
            "slug" => $slug,
        ]);
    }
}
