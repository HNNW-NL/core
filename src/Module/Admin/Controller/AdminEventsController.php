<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Entity\Common\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/events', name: 'admin.events.')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('ADMIN_VIEW_DASHBOARD')]
final class AdminEventsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('pages/admin/events/index.html.twig', [
            'events' => $em->getRepository(Event::class)->findBy([], ['startDatetime' => 'DESC'], 100),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('pages/admin/events/create.html.twig');
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    public function modify(string $id, EntityManagerInterface $em): Response
    {
        $event = $em->getRepository(Event::class)->find($id);
        if ($event === null) {
            throw $this->createNotFoundException('Evenement niet gevonden.');
        }
        return $this->render('pages/admin/events/modify.html.twig', [
            'id' => $id,
            'event' => $event,
        ]);
    }
}
