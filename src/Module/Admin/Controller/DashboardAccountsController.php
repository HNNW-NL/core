<?php

namespace App\Module\Admin\Controller;

use App\Entity\Account\Account;
use App\Form\Admin\AccountSearchType;
use App\Module\Admin\DTO\AccountSearchDisplayDTO;
use App\Module\Admin\DTO\AccountSearchDTO;
use App\Module\Admin\Service\Paginate\Adapters\PaginateAdapterMode;
use App\Twig\Components\Shared\Search\SearchDTO;
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
        $search->dto = AccountSearchDTO::class;
        $search->displayDTO = AccountSearchDisplayDTO::class;
        $search->entity = Account::class;
        $search->form = AccountSearchType::class;
        $search->orderBy = ['createdAt'=> 'DESC','id' => 'ASC'];

        return $this->render('pages/admin/accounts/index.html.twig',
        array(
            'search' => $search,
            'mode' => PaginateAdapterMode::OffsetMode
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
