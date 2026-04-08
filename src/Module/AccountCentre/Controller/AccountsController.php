<?php

namespace App\Module\AccountCentre\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/account', name: 'account.')]
final class AccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/account-centre/index.html.twig');
    }

    #[Route('/applications', name: 'applications', methods: ['GET'])]
    public function applications(): Response
    {
        return $this->render('pages/account-centre/applications.html.twig');
    }

    #[Route('/availability', name: 'availability', methods: ['GET'])]
    public function availability(): Response
    {
        return $this->render('pages/account-centre/availability.html.twig');
    }

    #[Route('/experience', name: 'experience', methods: ['GET'])]
    public function experience(): Response
    {
        return $this->render('pages/account-centre/experience.html.twig');
    }

    #[Route('/modify', name: 'modify', methods: ['GET'])]
    public function modify(): Response
    {
        return $this->render('pages/account-centre/modify.html.twig');
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    public function notifications(): Response
    {
        return $this->render('pages/account-centre/notifications.html.twig');
    }

    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(): Response
    {
        return $this->render('pages/account-centre/projects.html.twig');
    }

    #[Route('/reputation', name: 'reputation', methods: ['GET'])]
    public function reputation(): Response
    {
        return $this->render('pages/account-centre/reputation.html.twig');
    }

    #[Route('/reviews', name: 'reviews', methods: ['GET'])]
    public function reviews(): Response
    {
        return $this->render('pages/account-centre/reviews.html.twig');
    }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('pages/account-centre/settings.html.twig');
    }

    #[Route('/signoff', name: 'signoff', methods: ['GET'])]
    public function signoff(): Response
    {
        return $this->render('pages/account-centre/signoff.html.twig');
    }

    #[Route('/skills', name: 'skills', methods: ['GET'])]
    public function skills(): Response
    {
        return $this->render('pages/account-centre/skills.html.twig');
    }
}
