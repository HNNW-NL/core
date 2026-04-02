<?php

namespace App\Module\Main\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'main.home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('main/home/index.html.twig');
    }
}
