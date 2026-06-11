<?php

namespace App\Module\Admin\Controller;

use App\Entity\Common\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/statuses', name: 'admin.statuses.')]
final class DashboardStatusesController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $statuses = $em->getRepository(Status::class)->findAll();

        return $this->render('pages/admin/statuses/index.html.twig', [
            'statuses' => $statuses,
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('pages/admin/statuses/create.html.twig');
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    public function modify(string $id): Response
    {
        return $this->render('pages/admin/statuses/modify.html.twig', [
            'id' => $id,
        ]);
    }
}