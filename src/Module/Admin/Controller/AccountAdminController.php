<?php

namespace App\Module\Admin\Controller;

use App\Entity\User;
use App\Form\Org\AccountType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/accounts')]
class AccountAdminController extends AbstractController
{
    #[Route('', name: 'org_accounts_index')]
    public function index(
        Request $request,
        UserRepository $userRepository
    ): Response {
        $search = $request->query->get('search');
        $dbConnected = true;

        try {
            $accounts = $search
                ? $userRepository->findBySearch($search)
                : $userRepository->findAllForAdmin();
        } catch (\Throwable $e) {
            // If the database is not available (missing tables, etc.),
            // fall back to an empty list so the admin page still renders.
            $accounts = [];
            $dbConnected = false;
            $this->addFlash('warning', 'Database unavailable — showing empty accounts.');
        }

        return $this->render(
            'pages/org/accounts/index.html.twig',
            [
                'accounts' => $accounts,
                'search' => $search,
                'dbConnected' => $dbConnected,
            ]
        );
    }

    #[Route('/{id}', name: 'org_accounts_show', requirements: ['id' => '\\d+'])]
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

    #[Route('/new', name: 'org_accounts_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();

        $form = $this->createForm(
            AccountType::class,
            $user
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Account aangemaakt');

            return $this->redirectToRoute('org_accounts_index');
        }

        return $this->render('pages/org/accounts/edit.html.twig', [
            'form' => $form,
            'account' => $user
        ]);
    }

    #[Route('/{id}/edit', name: 'org_accounts_edit', requirements: ['id' => '\\d+'])]
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

    #[Route('/{id}/delete', name: 'org_accounts_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Account verwijderd');
        }

        return $this->redirectToRoute('org_accounts_index');
    }
}