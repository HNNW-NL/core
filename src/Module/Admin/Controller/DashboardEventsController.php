<?php

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/events', name: 'admin.events.')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardEventsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    #[IsGranted('ADMIN_VIEW_EVENTS')]
    public function index(): Response
    {
        return $this->render(
            'pages/admin/events/index.html.twig'
        );
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_EVENTS')]
    public function create(): Response
    {
        return $this->render(
            'pages/admin/events/create.html.twig'
        );
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_EVENTS')]
    public function modify(string $id): Response
    {
        return $this->render(
            'pages/admin/events/modify.html.twig',
            [
                'id' => $id,
            ]
        );
    }
}
