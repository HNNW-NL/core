<?php

namespace App\Module\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'auth.')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('auth/login/index.html.twig');
    }

    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('auth/register/index.html.twig');
    }

    #[Route('/password-reset', name: 'password_reset', methods: ['GET'])]
    public function passwordReset(): Response
    {
        return $this->render('auth/password-reset/index.html.twig');
    }
}
