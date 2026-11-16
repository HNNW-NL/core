<?php

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Module\Admin\Service\AccountOverview;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Repository\Common\StatusRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[Route('/admin/accounts', name: 'admin.accounts.')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('ADMIN_MANAGE_ACCOUNTS')]
final class AdminAccountsController extends AbstractController
{
    #[Route('', name: 'home', methods: ['GET'])]
    public function index(AccountOverview $overview): Response
    {
        return $this->render('pages/admin/accounts/index.html.twig', [
            'overview' => $overview->load(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['GET', 'POST'])]
    #[Route('/modify/{id}', name: 'modify', methods: ['GET', 'POST'])]
    public function modify(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, StatusRepository $statusRepository, ?string $id = null): Response
    {
        $account = $id === null ? new Account() : $this->findAccount($id, $em);
        if ($account->getAdmin() !== null) {
            throw $this->createAccessDeniedException();
        }
        $statuses = $statusRepository->findBy(['scope' => 'account'], ['name' => 'ASC']);
        $input = ['username' => $account->getUsername(), 'email' => $account->getEmail(), 'status' => (string) $account->getStatus()?->getId()];
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account-save-'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            foreach (array_keys($input) as $field) {
                $input[$field] = trim($request->request->getString($field));
            }
            $password = $id === null ? $request->request->getString('password') : '';
            $status = null;
            foreach ($statuses as $candidate) {
                if ((string) $candidate->getId() === $input['status']) {
                    $status = $candidate;
                    break;
                }
            }
            if ($input['username'] === '' || mb_strlen($input['username']) > 255
                || strlen($input['email']) > 255 || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)
                || $status === null || ($id === null && (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")))) {
                $error = 'Controleer de gebruikersnaam, het e-mailadres, de status en het wachtwoord.';
            } else {
                if ($account->getEmail() !== $input['email']) {
                    $account->unverifyEmail();
                }
                $account->setUsername($input['username'])->setEmail($input['email'])->setStatus($status);
                if ($id === null) {
                    $account->setPasswordHash($hasher->hashPassword($account, $password));
                    $profile = (new Profile())->setAccount($account)->setFirstName($input['username'])
                        ->setLastName('')->setDisplayName($input['username'])->setAvatarUrl('');
                    $em->persist($account);
                    $em->persist($profile);
                }
                try {
                    $em->flush();
                    return $this->redirectToRoute('admin.accounts.view', ['id' => (string) $account->getId()], 303);
                } catch (UniqueConstraintViolationException) {
                    $error = 'De gebruikersnaam of het e-mailadres is al in gebruik.';
                }
            }
        }

        return $this->render('pages/admin/accounts/modify.html.twig', compact('id', 'input', 'statuses', 'error'),
            new Response(status: $error === null ? 200 : 422));
    }

    #[Route('/delete/{id}', name: 'delete', methods: ['POST'])]
    public function delete(string $id, Request $request, EntityManagerInterface $em): Response
    {
        $account = $this->findAccount($id, $em);
        if ($account->getAdmin() !== null || !$this->isCsrfTokenValid('account-delete-'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $account->softDelete();
        $em->flush();

        return $this->redirectToRoute('admin.accounts.home', status: 303);
    }


    #[Route('/view/{id}', name: 'view', methods: ['GET'])]
    public function view(string $id, EntityManagerInterface $entityManager): Response
    {
        return $this->render('pages/admin/accounts/view.html.twig', ['account' => $this->findAccount($id, $entityManager)]);
    }

    private function findAccount(string $id, EntityManagerInterface $entityManager): Account
    {
        $account = Ulid::isValid($id) ? $entityManager->find(Account::class, Ulid::fromString($id)) : null;
        if (!$account instanceof Account || $account->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }

        return $account;
    }
}
