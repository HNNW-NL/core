<?php

namespace App\Module\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Account\Account;
use App\Entity\Common\Status;

#[Route('/auth', name: 'auth.')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET','POST'])]
    public function login(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $usernameOrEmail = (string)$request->request->get('username', '');
            $password = (string)$request->request->get('password', '');

            $repo = $em->getRepository(Account::class);
            $account = $repo->findOneBy(['username' => $usernameOrEmail]);
            if (!$account) {
                $account = $repo->findOneBy(['email' => $usernameOrEmail]);
            }

            if (!$account || !password_verify($password, $account->getPasswordHash() ?? '')) {
                $this->addFlash('error', 'Ongeldige gebruikersnaam/e-mail of wachtwoord.');
                return $this->redirectToRoute('auth.login', ['last' => $usernameOrEmail]);
            }

            $account->login();
            $em->persist($account);
            $em->flush();

            $request->getSession()->set('account_id', (string)$account->getId());

            return new RedirectResponse('/');
        }

        return $this->render('pages/auth/login.html.twig', [
            'last_username' => (string)$request->query->get('last', ''),
        ]);
    }

    #[Route('/register', name: 'register', methods: ['GET','POST'])]
    public function register(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $data = [
                'full_name' => trim((string)$request->request->get('full_name', '')),
                'username' => trim((string)$request->request->get('username', '')),
                'email' => trim((string)$request->request->get('email', '')),
            ];

            $password = (string)$request->request->get('password', '');
            $confirm = (string)$request->request->get('confirm_password', '');

            $errors = [];
            if ($data['username'] === '') {
                $errors['username'] = 'Kies een gebruikersnaam.';
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Voer een geldig e-mailadres in.';
            }
            if (strlen($password) < 8) {
                $errors['password'] = 'Wachtwoord moet minimaal 8 tekens bevatten.';
            }
            if ($password !== $confirm) {
                $errors['confirm_password'] = 'Wachtwoorden komen niet overeen.';
            }

            $repo = $em->getRepository(Account::class);
            if ($repo->findOneBy(['username' => $data['username']])) {
                $errors['username'] = 'Deze gebruikersnaam is al in gebruik.';
            }
            if ($repo->findOneBy(['email' => $data['email']])) {
                $errors['email'] = 'Dit e-mailadres is al in gebruik.';
            }

            if (!empty($errors)) {
                return $this->render('pages/auth/register.html.twig', [
                    'errors' => $errors,
                    'old' => $data,
                ]);
            }

            $statusRepo = $em->getRepository(Status::class);
            $defaultStatus = $statusRepo->findOneBy(['scope' => 'account']);
            if (!$defaultStatus) {
                $defaultStatus = new Status();
                $defaultStatus->setName('active');
                $defaultStatus->setScope('account');
                $defaultStatus->setColourHex('#10B981');
                $em->persist($defaultStatus);
            }

            $account = new Account();
            $account->setUsername($data['username']);
            $account->setEmail($data['email']);
            $account->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
            $account->setStatus($defaultStatus);

            $em->persist($account);
            $em->flush();

            $this->addFlash('success', 'Account aangemaakt. Je kunt nu inloggen.');
            return $this->redirectToRoute('auth.login', ['last' => $data['username']]);
        }

        return $this->render('pages/auth/register.html.twig');
    }

    #[Route('/forgot-password', name: 'forgotPassword', methods: ['GET'])]
    public function forgotPassword(): Response
    {
        return $this->render('pages/auth/forgot-password.html.twig');
    }

    #[Route('/reset-password/{token}/{email}', name: 'resetPassword', methods: ['GET'])]
    public function resetPassword(string $token, string $email): Response
    {
        return $this->render('emails/reset-password.html.twig',[
            'token' => $token,
            'email' => $email
        ]);
    }
}
