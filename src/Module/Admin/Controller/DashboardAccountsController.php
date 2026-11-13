<?php

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/accounts', name: 'admin.accounts.')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardAccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    #[IsGranted('ADMIN_VIEW_ACCOUNTS')]
    public function index(): Response
    {
        return $this->render(
            'pages/admin/accounts/index.html.twig'
        );
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    #[IsGranted('ADMIN_MANAGE_ACCOUNTS')]
    public function modify(string $id): Response
    {
        return $this->render(
            'pages/admin/accounts/modify.html.twig',
            [
                'id' => $id,
            ]
        );
    }
}
