<?php

namespace App\Module\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Auth\ResetPasswordToken;
use App\Entity\Auth\VerifyEmailToken;
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
    public function register(Request $request, EntityManagerInterface $em, MailerInterface $mailer, UrlGeneratorInterface $router): Response
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

            // Wrap in a transaction to prevent partial database data insertions
            $em->getConnection()->beginTransaction();
            try {
                // 1. Setup Account (Id is automatically instantiated as Uuid object in constructor)
                $account = new Account();
                $account->setUsername($data['username']);
                $account->setEmail($data['email']);
                $account->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
                $account->setStatus($defaultStatus);
                $em->persist($account);

                // 2. Setup Profile linked directly to Account
                $nameParts = explode(' ', $data['full_name'], 2);
                $firstName = $nameParts[0] !== '' ? $nameParts[0] : $data['username'];
                $lastName = $nameParts[1] ?? ' ';

                $profile = new Profile();
                $profile->setAccount($account);
                $profile->setFirstName($firstName);
                $profile->setLastName($lastName);
                $profile->setDisplayName($data['username']);
                $profile->setAvatarUrl('https://ui-avatars.com/api/?name=' . urlencode($firstName) . '&background=facc15&color=000');
                $profile->setPoints(0);
                $em->persist($profile);

                // 3. Setup Verification Token
                $plainToken = bin2hex(random_bytes(32));
                $tokenHash = password_hash($plainToken, PASSWORD_DEFAULT);

                $verifyToken = new VerifyEmailToken();
                $verifyToken->setAccount($account);
                $verifyToken->setEmailToVerify($account->getEmail());
                $verifyToken->setTokenHash($tokenHash);
                $em->persist($verifyToken);

                // Sync all relationships securely to the database engine
                $em->flush();

                // 4. Generate Absolute URL Link & Dispatch Mail
                $verifyLink = $router->generate('auth.verifyEmail', [
                    'token' => $plainToken,
                    'email' => $account->getEmail(),
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $mailer->send(
                    (new Email())
                        ->from('no-reply@example.com')
                        ->to($account->getEmail())
                        ->subject('Bevestig je e-mailadres')
                        ->text("Klik op deze link om je e-mailadres te bevestigen:\n\n".$verifyLink."\n\nDeze link is tijdelijk geldig.")
                );

                $em->getConnection()->commit();

            } catch (\Exception $e) {
                $em->getConnection()->rollBack();

                // Diagnostic dump to identify missing entity parameters easily
                dd($e->getMessage(), $e->getTraceAsString());

                $this->addFlash('error', 'Er is iets misgegaan tijdens de registratie. Probeer het opnieuw.');
                return $this->render('pages/auth/register.html.twig', [
                    'old' => $data,
                ]);
            }

            $this->addFlash('success', 'Account aangemaakt. Je kunt nu inloggen.');
            return $this->redirectToRoute('auth.login', ['last' => $data['username']]);
        }

        return $this->render('pages/auth/register.html.twig');
    }

    #[Route('/forgot-password', name: 'forgotPassword', methods: ['GET','POST'])]
    public function forgotPassword(Request $request, EntityManagerInterface $em, MailerInterface $mailer, UrlGeneratorInterface $router): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string)$request->request->get('email', ''));

            $this->addFlash('success', 'Als dat e-mailadres bestaat, is er een e-mail verzonden met instructies.');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->redirectToRoute('auth.forgotPassword');
            }

            $repo = $em->getRepository(Account::class);
            $account = $repo->findOneBy(['email' => $email]);

            if (!$account) {
                return $this->redirectToRoute('auth.forgotPassword');
            }

            $plainToken = bin2hex(random_bytes(32));
            $tokenHash = password_hash($plainToken, PASSWORD_DEFAULT);

            $token = new ResetPasswordToken();
            $token->setAccount($account);
            $token->setTokenHash($tokenHash);
            $em->persist($token);
            $em->flush();

            $resetLink = $router->generate('auth.resetPassword', ['token' => $plainToken, 'email' => $account->getEmail()], UrlGeneratorInterface::ABSOLUTE_URL);

            $emailMessage = (new Email())
                ->from('no-reply@example.com')
                ->to($account->getEmail())
                ->subject('Wachtwoord resetten')
                ->text("Klik op deze link om je wachtwoord te resetten:\n\n".$resetLink."\n\nDeze link is tijdelijk geldig.");

            $mailer->send($emailMessage);

            return $this->redirectToRoute('auth.forgotPassword');
        }

        return $this->render('pages/auth/forgot-password.html.twig');
    }

    #[Route('/reset-password/{token}/{email}', name: 'resetPassword', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token, string $email, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Account::class);
        $account = $repo->findOneBy(['email' => $email]);

        if (!$account) {
            $this->addFlash('error', 'Ongeldige of verlopen reset link.');
            return $this->redirectToRoute('auth.forgotPassword');
        }

        $tokenRepo = $em->getRepository(ResetPasswordToken::class);
        $tokens = $tokenRepo->findBy(['account' => $account, 'isUsed' => false]);

        $matchedToken = null;
        $now = new \DateTimeImmutable();
        foreach ($tokens as $candidate) {
            if ($candidate->getExpiresAt() < $now) {
                continue;
            }

            if (password_verify($token, $candidate->getTokenHash() ?? '')) {
                $matchedToken = $candidate;
                break;
            }
        }

        if (!$matchedToken) {
            $this->addFlash('error', 'Ongeldige of verlopen reset link.');
            return $this->redirectToRoute('auth.forgotPassword');
        }

        if ($request->isMethod('POST')) {
            $password = (string)$request->request->get('password', '');
            $confirm = (string)$request->request->get('confirm_password', '');

            $errors = [];
            if (strlen($password) < 8) {
                $errors[] = 'Wachtwoord moet minimaal 8 tekens bevatten.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Wachtwoorden komen niet overeen.';
            }

            if (!empty($errors)) {
                return $this->renderResetPasswordPage($token, $email, $errors);
            }

            $account->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
            $matchedToken->markAsUsed();

            $em->persist($account);
            $em->persist($matchedToken);
            $em->flush();

            $this->addFlash('success', 'Wachtwoord succesvol gereset. Je kunt nu inloggen.');
            return $this->redirectToRoute('auth.login');
        }

        return $this->renderResetPasswordPage($token, $email, []);
    }

    #[Route('/verify-email/{token}/{email}', name: 'verifyEmail', methods: ['GET'])]
    public function verifyEmail(string $token, string $email, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(Account::class);
        $account = $repo->findOneBy(['email' => $email]);

        if (!$account) {
            $this->addFlash('error', 'Ongeldige of verlopen verificatielink.');
            return $this->redirectToRoute('auth.login');
        }

        $tokenRepo = $em->getRepository(VerifyEmailToken::class);
        $tokens = $tokenRepo->findBy(['account' => $account, 'isUsed' => false]);

        $matchedToken = null;
        $now = new \DateTimeImmutable();
        foreach ($tokens as $candidate) {
            if ($candidate->getExpiresAt() < $now) {
                continue;
            }

            if (password_verify($token, $candidate->getTokenHash() ?? '')) {
                $matchedToken = $candidate;
                break;
            }
        }

        if (!$matchedToken) {
            $this->addFlash('error', 'Ongeldige of verlopen verificatielink.');
            return $this->redirectToRoute('auth.login');
        }

        $account->verifyEmail();
        $matchedToken->markAsUsed();

        $em->persist($account);
        $em->persist($matchedToken);
        $em->flush();

        $this->addFlash('success', 'E-mailadres bevestigd. Je kunt nu inloggen.');
        return $this->redirectToRoute('auth.login');
    }

    private function renderResetPasswordPage(string $token, string $email, array $errors = []): Response
    {
        $action = $this->generateUrl('auth.resetPassword', ['token' => $token, 'email' => $email]);

        $html = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Wachtwoord resetten</title></head><body>';
        $html .= '<h1>Wachtwoord resetten</h1>';

        if (!empty($errors)) {
            $html .= '<div class="form-errors" role="alert"><ul>';
            foreach ($errors as $error) {
                $html .= '<li>' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
        $html .= '<label for="password">Nieuw wachtwoord</label><br>';
        $html .= '<input id="password" name="password" type="password" minlength="8" required><br>';
        $html .= '<label for="confirm_password">Bevestig wachtwoord</label><br>';
        $html .= '<input id="confirm_password" name="confirm_password" type="password" minlength="8" required><br>';
        $html .= '<button type="submit">Wachtwoord opslaan</button>';
        $html .= '</form></body></html>';

        return new Response($html);
    }
}
