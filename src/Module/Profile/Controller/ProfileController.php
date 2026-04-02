<?php

namespace App\Module\Profile\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profile', name: 'profile.')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'view', methods: ['GET'])]
    public function view(): Response
    {
        return $this->render('profile/view/index.html.twig');
    }

    #[Route('/modify', name: 'modify', methods: ['GET'])]
    public function modify(): Response
    {
        return $this->render('profile/modify/index.html.twig');
    }
}
