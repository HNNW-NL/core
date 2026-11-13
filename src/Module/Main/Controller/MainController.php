<?php

namespace App\Module\Main\Controller;

use App\Entity\Common\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'main.')]
final class MainController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/main/index.html.twig');
    }

    #[Route('/about', name: 'about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('pages/main/about.html.twig');
    }

    #[Route('/contact', name: 'contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('pages/main/contact.html.twig');
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(EntityManagerInterface $em): Response
    {
        $events = $em->getRepository(Event::class)->findBy(
            ['deletedAt' => null],
            ['startDatetime' => 'ASC']
        );

        return $this->render('pages/main/events/index.html.twig', ['events' => $events]);
    }

    #[Route('/events/{slug}', name: 'event', methods: ['GET'])]
    public function viewEvent(string $slug, EntityManagerInterface $em): Response
    {
        $event = $em->getRepository(Event::class)->findOneBy([
            'slug' => $slug,
            'deletedAt' => null,
        ]);

        if ($event === null) {
            throw $this->createNotFoundException('Evenement niet gevonden.');
        }

        return $this->render('pages/main/events/detail.html.twig', [
            'slug' => $slug,
            'event' => $event,
        ]);
    }
}
