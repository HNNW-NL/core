<?php

namespace App\Module\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Auth\ResetPasswordToken;
use App\Entity\Auth\VerifyEmailToken;
use App\Entity\Common\Status;
use App\Module\Auth\Service\PasswordResetRequestLimiter;
use App\Module\Auth\Service\PasswordResetService;
use Psr\Log\LoggerInterface;

#[Route('/auth', name: 'auth.')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // De POST naar deze route wordt onderschept en afgehandeld door
        // App\Security\AccountAuthenticator (zie config/packages/security.yaml),
        // dus hier hoeft alleen nog het GET-formulier gerenderd te worden.
        return $this->render('pages/auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername() ?: $request->query->get('last', ''),
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        // Wordt afgehandeld door de firewall's logout listener; komt hier nooit aan.
        throw new \LogicException('Deze route wordt afgehandeld door de Symfony Security logout listener.');
    }

    #[Route('/register', name: 'register', methods: ['GET','POST'])]
    public function register(Request $request, EntityManagerInterface $em, MailerInterface $mailer, UrlGeneratorInterface $router, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('auth_register', (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ongeldig formulier-token.');
            }

            $data = [
                'full_name' => trim((string)$request->request->get('full_name', '')),
                'username' => trim((string)$request->request->get('username', '')),
                'email' => trim((string)$request->request->get('email', '')),
            ];

            $password = (string)$request->request->get('password', '');
            $confirm = (string)$request->request->get('confirm_password', '');

            $errors = [];
            if (mb_strlen($data['full_name']) > 100) {
                $errors['full_name'] = 'De naam is te lang.';
            }
            if (mb_strlen($data['username']) < 3 || mb_strlen($data['username']) > 30) {
                $errors['username'] = 'Gebruik een gebruikersnaam van 3 tot 30 tekens.';
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
            $defaultStatus = $statusRepo->findOneBy(['scope' => 'account', 'name' => 'active']);
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
                // 1. Setup Account (Id is automatically instantiated as Ulid object in constructor)
                $account = new Account();
                $account->setUsername($data['username']);
                $account->setEmail($data['email']);
                $account->setPasswordHash($passwordHasher->hashPassword($account, $password));
                $account->setStatus($defaultStatus);
                $em->persist($account);

                // 2. Setup Profile linked directly to Account
                $nameParts = explode(' ', $data['full_name'], 2);
                $firstName = $nameParts[0] !== '' ? $nameParts[0] : $data['username'];
                $lastName = $nameParts[1] ?? '';

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

                return $this->render('pages/auth/register.html.twig', [
                    'errors' => [
                        'general' => 'Er is iets misgegaan tijdens de registratie. Probeer het opnieuw.'],
                    'old' => $data,
                ]);
            }

            $this->addFlash('success', 'Account aangemaakt. Controleer je e-mail voor de verificatielink.');
            return $this->redirectToRoute('auth.login', ['last' => $data['username']]);
        }

        return $this->render('pages/auth/register.html.twig');
    }

    #[Route('/forgot-password', name: 'forgotPassword', methods: ['GET','POST'])]
    public function forgotPassword(
        Request $request,
        PasswordResetService $passwordReset,
        PasswordResetRequestLimiter $requestLimiter,
        LoggerInterface $logger,
    ): Response
    {
        $email = '';
        $errors = [];
        $status = Response::HTTP_OK;
        $headers = ['Cache-Control' => 'no-store'];

        if ($request->isMethod('POST')) {
            $request->getSession()->getFlashBag()->get('success');
            $input = $request->request->all();
            $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
            $csrfToken = is_string($input['_token'] ?? null) ? $input['_token'] : '';
            $status = Response::HTTP_UNPROCESSABLE_ENTITY;

            if (!$this->isCsrfTokenValid('auth_forgot_password', $csrfToken)) {
                $errors['general'] = 'Het formulier is verlopen. Probeer het opnieuw.';
            }
            if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Voer een geldig e-mailadres in.';
            }

            if ($errors === []) {
                try {
                    $limit = $requestLimiter->consume($request->getClientIp(), $email);
                    if ($limit->isAccepted()) {
                        try {
                            $passwordReset->sendResetLink($email);
                        } catch (\Throwable $exception) {
                            // Exception messages may contain SMTP credentials or the recovery URL.
                            $logger->error('Password reset request could not be completed.', ['exception_class' => $exception::class]);
                        }

                        $this->addFlash('success', 'Als er een account met dit e-mailadres bestaat, ontvang je een e-mail met een herstellink.');

                        return $this->redirectToRoute('auth.forgotPassword', status: Response::HTTP_SEE_OTHER);
                    }

                    $status = Response::HTTP_TOO_MANY_REQUESTS;
                    $headers['Retry-After'] = (string) max(1, $limit->getRetryAfter()->getTimestamp() - time());
                    $errors['general'] = 'Je hebt te vaak een herstellink aangevraagd. Wacht 15 minuten en probeer het opnieuw.';
                } catch (\Throwable $exception) {
                    $logger->error('Password reset request limiter is unavailable.', ['exception_class' => $exception::class]);
                    $status = Response::HTTP_SERVICE_UNAVAILABLE;
                    $errors['general'] = 'Een herstellink aanvragen is tijdelijk niet mogelijk. Probeer het later opnieuw.';
                }
            }
        }

        return $this->render('pages/auth/forgot-password.html.twig', [
            'email' => $email,
            'errors' => $errors,
        ], new Response(status: $status, headers: $headers));
    }

    #[Route('/reset-password/{token}/{email}', name: 'resetPassword', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token, string $email, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
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
            if (!$this->isCsrfTokenValid('auth_reset_password', (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ongeldig formulier-token.');
            }
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

            $account->setPasswordHash($passwordHasher->hashPassword($account, $password));
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
        return $this->render('pages/auth/reset-password.html.twig', [
            'token' => $token,
            'email' => $email,
            'errors' => $errors,
        ]);
    }
}
