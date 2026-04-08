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
        return $this->render('pages/auth/login.html.twig');
    }

    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('pages/auth/register.html.twig');
    }

    #[Route('/forgot-password', name: 'forgotPassword', methods: ['GET'])]
    public function forgotPassword(): Response
    {
        return $this->render('pages/auth/forgot-password.html.twig');
    }

    #[Route('/reset-password/{token}/{email}', name: 'resetPassword', methods: ['GET'])]
    public function resetPassword(string $token, string $email): Response
    {
        return $this->render('emails/reset-password.html.twig',[
            'token' => $token,
            'email' => $email
        ]);
    }
}
