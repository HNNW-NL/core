<?php

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/events', name: 'admin.events.')]
final class DashboardEventsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/admin/events/index.html.twig');
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('pages/admin/events/create.html.twig');
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    public function modify(string $id): Response
    {
        return $this->render('pages/admin/events/modify.html.twig', [
            'id' => $id
        ]);
    }
}
