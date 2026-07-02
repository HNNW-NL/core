<?php

namespace Templates\Pages\Admin\Accounts\Controller;

use App\Entity\User;
use App\Repository\Org\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/org/accounts')]
class AccountController extends AbstractController
{
    #[Route('', name: 'org_accounts')]
    public function index(Request $request, AccountRepository $repository): Response
    {
        $q = $request->query->get('q');

        return $this->render(
            'pages/org/index.html.twig',
            [
                'users' => $repository->findBySearch($q)
            ]
        );
    }

    #[Route('/{id}', name: 'org_account_show')]
    public function show(User $user): Response
    {
        return $this->render(
            'pages/org/show.html.twig',
            [
                'user' => $user
            ]
        );
    }

    #[Route('/{id}/edit', name: 'org_account_edit')]
    public function edit(
        ?User $user,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $isNew = false;
        if (!$user) {
            $user = new User();
            $isNew = true;
        }

        if ($request->isMethod('POST')) {
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            $user->setEmail($request->request->get('email'));
            $user->setActive((bool)$request->request->get('active'));

            if ($isNew) {
                $em->persist($user);
            }

            $em->flush();

            return $this->redirectToRoute('org_accounts');
        }

        return $this->render('pages/org/edit.html.twig', ['user' => $user]);
    }

    #[Route('/create', name: 'org_account_create')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $user = new User();

        if ($request->isMethod('POST')) {
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            $user->setEmail($request->request->get('email'));
            $user->setActive((bool)$request->request->get('active'));

            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('org_accounts');
        }

        return $this->render('pages/org/edit.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/delete', name: 'org_account_delete')]
    public function delete(
        User $user,
        EntityManagerInterface $em
    ): Response {
        $em->remove($user);
        $em->flush();

        return $this->redirectToRoute(
            'org_accounts'
        );
    }
}