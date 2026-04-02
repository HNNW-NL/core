<?php

namespace App\Module\Reputation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reputation', name: 'reputation.')]
final class ReputationController extends AbstractController
{
    #[Route('/ratings', name: 'ratings', methods: ['GET'])]
    public function ratings(): Response
    {
        return $this->render('reputation/ratings/index.html.twig');
    }
}
