<?php

namespace App\Module\Auth\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Module\Auth\Service\PasswordResetRequestLimiter;
use App\Module\Auth\Service\PasswordResetService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Module\Auth\Service\RegistrationService;
use App\Module\Auth\Exception\RegistrationValidationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

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
            'last_username' => $authenticationUtils->getLastUsername() ?: (is_string($request->query->all()['last'] ?? null) ? $request->query->all()['last'] : ''),
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): never
    {
        // Wordt afgehandeld door de firewall's logout listener; komt hier nooit aan.
        throw new \LogicException('Deze route wordt afgehandeld door de Symfony Security logout listener.');
    }

    #[Route('/register', name: 'register', methods: ['GET','POST'])]
    public function register(Request $request, RegistrationService $registration, LoggerInterface $logger): Response
    {
        $data = ['full_name' => '', 'username' => '', 'email' => ''];
        $errors = [];
        $terms = false;
        if ($request->isMethod('POST')) {
            foreach (array_keys($data) as $field) {
                $data[$field] = trim($this->formString($request, $field));
            }
            $data['email'] = strtolower($data['email']);
            $terms = in_array($this->formString($request, 'terms'), ['1', 'on', 'true'], true);
            if (!$this->isCsrfTokenValid('auth_register', $this->formString($request, '_token'))) {
                $errors['general'] = 'Het formulier is verlopen. Probeer het opnieuw.';
            } else {
                try {
                    $registration->register($data, $this->formString($request, 'password'), $this->formString($request, 'confirm_password'), $terms);
                    $this->addFlash('success', 'Account aangemaakt. Controleer je e-mail om je e-mailadres te bevestigen.');

                    return $this->redirectToRoute('auth.login', status: Response::HTTP_SEE_OTHER);
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
    public function resetPassword(
        Request $request,
        #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $email,
        PasswordResetService $passwordReset,
        Security $security,
        LoggerInterface $logger,
    ): Response {
        if ($passwordReset->findValidToken($token, $email) === null) {
            return $this->renderResetPasswordPage($token, $email, validLink: false);
        }
        if ($request->isMethod('POST')) {
            $password = $this->formString($request, 'password');
            $confirmation = $this->formString($request, 'confirm_password');
            $errors = [];
            if (!$this->isCsrfTokenValid('auth_reset_password', $this->formString($request, '_token'))) {
                $errors['general'] = 'Het formulier is verlopen. Probeer het opnieuw.';
            }
            if (mb_strlen($password) < 8) {
                $errors['password'] = 'Gebruik minimaal 8 tekens voor je wachtwoord.';
            } elseif (strlen($password) > 4096) {
                $errors['password'] = 'Je wachtwoord is te lang.';
            }
            if ($password !== $confirmation) {
                $errors['confirm_password'] = 'De wachtwoorden komen niet overeen.';
            }
            if ($errors !== []) {
                return $this->renderResetPasswordPage($token, $email, $errors);
            }
            try {
                if (!$passwordReset->resetPassword($token, $email, $password)) {
                    return $this->renderResetPasswordPage($token, $email, validLink: false);
                }
            } catch (\Throwable $exception) {
                $logger->error('Password reset could not be completed.', ['exception_class' => $exception::class]);

                return $this->renderResetPasswordPage($token, $email,
                    ['general' => 'Je wachtwoord kon tijdelijk niet worden opgeslagen. Probeer het opnieuw.'], status: Response::HTTP_SERVICE_UNAVAILABLE);
            }
            $response = $this->getUser() !== null ? $security->logout(false) : null;
            $this->addFlash('success', 'Je wachtwoord is gewijzigd. Je kunt nu inloggen met je nieuwe wachtwoord.');
            $response ??= $this->redirectToRoute('auth.login');
            $response->setStatusCode(Response::HTTP_SEE_OTHER);
            $response->headers->set('Location', $this->generateUrl('auth.login'));

            return $response;
        }

        return $this->renderResetPasswordPage($token, $email);
    }

    #[Route('/verify-email/{token}/{email}', name: 'verifyEmail', methods: ['GET'])]
    public function verifyEmail(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $email, RegistrationService $registration): Response
    {
        if (!$registration->verifyEmail($token, $email)) {
            $this->addFlash('error', 'Ongeldige of verlopen verificatielink.');
        } else {
            $this->addFlash('success', 'E-mailadres bevestigd. Je kunt nu inloggen.');
        }

        return $this->redirectToRoute('auth.login', status: Response::HTTP_SEE_OTHER);
    }

    private function formString(Request $request, string $field): string
    {
        $value = $request->request->all()[$field] ?? '';

        return is_string($value) ? $value : '';
    }

    private function renderResetPasswordPage(string $token, string $email, array $errors = [], bool $validLink = true, ?int $status = null): Response
    {
        $status ??= !$validLink ? Response::HTTP_BAD_REQUEST : ($errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);

        return $this->render('pages/auth/reset-password.html.twig', [
            'token' => $token,
            'email' => $email,
            'errors' => $errors,
            'valid_link' => $validLink,
            'sensitive_auth_page' => true,
        ], new Response(status: $status));
    }
}
