<?php

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/accounts', name: 'admin.accounts.')]
final class DashboardAccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Vang zoek- en filterparameters op uit de URL (query string)
        $search = $request->query->get('q', '');
        $status = $request->query->get('status', '');
        $page = max(1, $request->query->getInt('page', 1));

        // TODO: Vervang dit straks met echte repository-logica voor AD02-B / datakoppeling
        $accounts = []; 

        return $this->render('pages/admin/accounts/index.html.twig', [
            'accounts' => $accounts,
            'search' => $search,
            'status' => $status,
            'currentPage' => $page,
        ]);
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    public function modify(string $id): Response
    {
        return $this->render('pages/admin/accounts/modify.html.twig', [
            'id' => $id
        ]);
    }
}