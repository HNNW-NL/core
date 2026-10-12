<?php

namespace App\Module\Admin\Controller;

use App\Entity\Log\AuditLog;
use App\Form\Admin\AuditLogSearchType;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use App\Repository\Log\AuditLogRepository;
use App\Twig\Components\Shared\SearchDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/accounts', name: 'admin.accounts.')]
final class DashboardAccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(): Response
    {
        $search = new SearchDTO();
        $search->dto = AuditLogSearchDTO::class;
        $search->displayDTO = AuditLogSearchDTO::class;
        $search->entity = AuditLog::class;
        $search->form = AuditLogSearchType::class;
        return $this->render('pages/admin/accounts/index.html.twig',
        array(
            'search' => $search
        ));
    }

    #[Route('/modify/{id}', name: 'modify', methods: ['GET'])]
    public function modify(string $id): Response
    {
        return $this->render('pages/admin/accounts/modify.html.twig', [
            'id' => $id
        ]);
    }
}
