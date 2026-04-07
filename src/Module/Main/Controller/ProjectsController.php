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
        return $this->render('pages/main/index.html.twig');
    }
}
