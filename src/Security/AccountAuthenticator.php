<?php

namespace App\Security;

use App\Entity\Account\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Neemt de POST naar /auth/login over van de firewall, zodra dit request
 * binnenkomt (dus vóórdat AuthController::login() aan bod komt). Vervangt
 * de oude handmatige password_verify()-check zodat inloggen daadwerkelijk
 * via Symfony Security verloopt en Security::getUser() overal werkt.
 */
final class AccountAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'auth.login' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('username', ''));
        $password = (string) $request->request->get('password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');

        $request->getSession()->set('_last_username', $identifier);

        return new Passport(
            new UserBadge($identifier),
            new PasswordCredentials($password),
            [new CsrfTokenBadge('authenticate', $csrfToken)]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $account = $token->getUser();

        if ($account instanceof Account) {
            $account->login();
            $this->entityManager->persist($account);
            $this->entityManager->flush();
        }

        return new RedirectResponse('/');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Ongeldige gebruikersnaam/e-mail of wachtwoord.');

        $lastUsername = (string) $request->getSession()->get('_last_username', '');

        return new RedirectResponse(
            $this->urlGenerator->generate('auth.login', ['last' => $lastUsername])
        );
    }
}