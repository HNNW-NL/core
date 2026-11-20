<?php

namespace App\Module\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Account\Account;
use App\Entity\Auth\VerifyEmailToken;
use App\Module\Auth\Service\PasswordResetService;
use App\Module\Auth\Service\RegistrationService;
use App\Module\Auth\Exception\RegistrationValidationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[Route('/auth', name: 'auth.')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        // De POST naar deze route wordt onderschept en afgehandeld door
        // App\Security\AccountAuthenticator (zie config/packages/security.yaml),
        // dus hier hoeft alleen nog het GET-formulier gerenderd te worden.
        return $this->render('pages/auth/login.html.twig', [
            'last_username' => $request->query->get('last', ''),
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): never
    {
        // Wordt afgehandeld door de firewall's logout listener; komt hier nooit aan.
        throw new \LogicException('Deze route wordt afgehandeld door de Symfony Security logout listener.');
    }

    #[Route('/register', name: 'register', methods: ['GET', 'POST'])]
    public function register(Request $request, RegistrationService $registration, LoggerInterface $logger): Response
    {
        $data = ['full_name' => '', 'username' => '', 'email' => ''];
        $errors = [];
        $terms = false;
        if ($request->isMethod('POST')) {
            foreach (array_keys($data) as $field) {
                $data[$field] = trim($request->request->getString($field));
            }
            $data['email'] = strtolower($data['email']);
            $terms = in_array($request->request->getString('terms'), ['1', 'on', 'true'], true);
            if (!$this->isCsrfTokenValid('register', $request->request->getString('_csrf_token'))) {
                $errors['general'] = 'Het formulier is verlopen. Probeer het opnieuw.';
            } else {
                try {
                    $account = $registration->register($data, $request->request->getString('password'), $request->request->getString('confirm_password'), $terms);
                    $this->addFlash('success', 'Account aangemaakt. Controleer je e-mail om je e-mailadres te bevestigen.');

                    return $this->redirectToRoute('auth.login', ['last' => $account->getEmail()], Response::HTTP_SEE_OTHER);
                } catch (RegistrationValidationException $exception) {
                    $errors = $exception->errors;
                } catch (UniqueConstraintViolationException) {
                    $errors['general'] = 'Dit e-mailadres of deze gebruikersnaam is al in gebruik.';
                } catch (\Throwable $exception) {
                    $logger->error('Registration could not be completed.', ['exception_class' => $exception::class]);
                    $errors['general'] = 'Registreren is tijdelijk niet mogelijk. Probeer het opnieuw.';
                }
            }
        }

        return $this->render('pages/auth/register.html.twig', ['old' => $data, 'errors' => $errors, 'terms' => $terms],
            new Response(status: $errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/forgot-password', name: 'forgotPassword', methods: ['GET','POST'])]
    public function forgotPassword(Request $request, PasswordResetService $passwordReset, LoggerInterface $logger): Response
    {
        $email = '';
        $errors = [];

        if ($request->isMethod('POST')) {
            $email = trim($request->request->getString('email'));

            if (!$this->isCsrfTokenValid('forgot_password', $request->request->getString('_csrf_token'))) {
                $errors['general'] = 'Het formulier is verlopen. Probeer het opnieuw.';
            }
            if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Voer een geldig e-mailadres in.';
            }

            if ($errors === []) {
                try {
                    $passwordReset->sendResetLink($email);
                } catch (TransportExceptionInterface) {
                    // Keep account existence private, including when delivery fails.
                    // Transport exceptions can contain the reset URL: never log them.
                    $logger->error('Password reset email delivery failed.');
                }

                $this->addFlash('success', 'Als er een account met dit e-mailadres bestaat, ontvang je een e-mail met een herstellink.');

                return $this->redirectToRoute('auth.forgotPassword', status: Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('pages/auth/forgot-password.html.twig', [
            'email' => $email,
            'errors' => $errors,
        ], new Response(status: $errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/reset-password/{token}/{email}', name: 'resetPassword', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token, string $email, PasswordResetService $passwordReset): Response
    {
        if ($passwordReset->findValidToken($token, $email) === null) {
            return $this->renderResetPasswordPage($token, $email, validLink: false);
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->getString('password');
            $confirm = $request->request->getString('confirm_password');

            $errors = [];
            if (!$this->isCsrfTokenValid('reset_password', $request->request->getString('_csrf_token'))) {
                $errors['general'] = 'Het formulier is verlopen. Probeer het opnieuw.';
            }
            if (mb_strlen($password) < 8) {
                $errors['password'] = 'Gebruik minimaal 8 tekens voor je wachtwoord.';
            } elseif (strlen($password) > 4096) {
                $errors['password'] = 'Je wachtwoord is te lang. Kies een korter wachtwoord.';
            }
            if ($password !== $confirm) {
                $errors['confirm_password'] = 'De wachtwoorden komen niet overeen.';
            }

            if ($errors !== []) {
                return $this->renderResetPasswordPage($token, $email, $errors);
            }

            if (!$passwordReset->resetPassword($token, $email, $password)) {
                return $this->renderResetPasswordPage($token, $email, validLink: false);
            }

            $this->addFlash('success', 'Je wachtwoord is gewijzigd. Je kunt nu inloggen met je nieuwe wachtwoord.');

            $response = $this->redirectToRoute('auth.login', status: Response::HTTP_SEE_OTHER);
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        }

        return $this->renderResetPasswordPage($token, $email);
    }

    #[Route('/verify-email/{token}/{email}', name: 'verifyEmail', methods: ['GET'])]
    public function verifyEmail(string $token, string $email, EntityManagerInterface $em): Response
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/', $token)) {
            $this->addFlash('error', 'Ongeldige of verlopen verificatielink.');
            return $this->redirectToRoute('auth.login');
        }
        $repo = $em->getRepository(Account::class);
        $account = $repo->findOneBy(['email' => $email, 'deletedAt' => null]);

        if (!$account) {
            $this->addFlash('error', 'Ongeldige of verlopen verificatielink.');
            return $this->redirectToRoute('auth.login');
        }

        $tokenRepo = $em->getRepository(VerifyEmailToken::class);
        $tokens = $tokenRepo->findBy(['account' => $account, 'isUsed' => false]);

        $matchedToken = null;
        $now = new \DateTimeImmutable();
        foreach ($tokens as $candidate) {
            if ($candidate->isUsed() || $candidate->getExpiresAt() <= $now || $candidate->getEmailToVerify() !== $email) {
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

    private function renderResetPasswordPage(string $token, string $email, array $errors = [], bool $validLink = true): Response
    {
        $status = !$validLink ? Response::HTTP_BAD_REQUEST : ($errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);

        return $this->render('pages/auth/reset-password.html.twig', [
            'token' => $token,
            'email' => $email,
            'errors' => $errors,
            'valid_link' => $validLink,
        ], new Response(status: $status, headers: [
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
        ]));
    }
}
