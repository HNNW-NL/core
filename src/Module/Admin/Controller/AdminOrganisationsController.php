<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Entity\Org\Organisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/organisations', name: 'admin.organisations.')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('ADMIN_MANAGE_ORGANISATIONS')]
final class AdminOrganisationsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('pages/admin/organisations/index.html.twig', [
            'organisations' => $em->getRepository(Organisation::class)->findBy([], ['createdAt' => 'DESC'], 100),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        return $this->render('pages/admin/organisations/create.html.twig');
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    public function modify(string $id, EntityManagerInterface $em): Response
    {
        $organisation = $em->getRepository(Organisation::class)->find($id);
        if ($organisation === null) {
            throw $this->createNotFoundException('Organisatie niet gevonden.');
        }
        return $this->render('pages/admin/organisations/modify.html.twig', [
            'id' => $id,
            'organisation' => $organisation,
        ]);
    }
}
