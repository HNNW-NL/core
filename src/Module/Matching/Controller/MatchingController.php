<?php

namespace App\Module\Matching\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/matching', name: 'matching.')]
final class MatchingController extends AbstractController
{
    #[Route('/overview', name: 'overview', methods: ['GET'])]
    public function overview(): Response
    {
        return $this->render('matching/overview/index.html.twig');
    }

    #[Route('/results', name: 'results', methods: ['GET'])]
    public function results(): Response
    {
        return $this->render('matching/results/index.html.twig');
    }
}
