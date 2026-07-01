<?php

namespace Templates\Pages\Admin\Accounts\Controller;

use App\Entity\User;
use App\Form\Org\AccountType;
use App\Repository\Org\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/accounts')]
#[IsGranted('ROLE_ADMIN')]
class AccountAdminController extends AbstractController
{
    #[Route('', name: 'org_accounts_index')]
    public function index(
        Request $request,
        AccountRepository $userRepository
    ): Response {
        $search = $request->query->get('search');

        $accounts = $search
            ? $userRepository->findBySearch($search)
            : $userRepository->findAllForAdmin();

        return $this->render(
            'pages/org/accounts/index.html.twig',
            [
                'accounts' => $accounts,
                'search' => $search
            ]
        );
    }

    #[Route('/{id}', name: 'org_accounts_show')]
    public function show(
        User $user
    ): Response {
        return $this->render(
            'pages/org/accounts/show.html.twig',
            [
                'account' => $user
            ]
        );
    }

    #[Route('/{id}/edit', name: 'org_accounts_edit')]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(
            AccountType::class,
            $user
        );

        $form->handleRequest($request);

        if (
            $form->isSubmitted()
            && $form->isValid()
        ) {
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Account succesvol bijgewerkt'
            );

            return $this->redirectToRoute(
                'org_accounts_index'
            );
        }

        return $this->render(
            'pages/org/accounts/edit.html.twig',
            [
                'form' => $form,
                'account' => $user
            ]
        );
    }
}