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
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Neemt de POST naar /auth/login over van de firewall, zodra dit request
 * binnenkomt (dus vóórdat AuthController::login() aan bod komt). Vervangt
 * de oude handmatige password_verify()-check zodat inloggen daadwerkelijk
 * via Symfony Security verloopt en Security::getUser() overal werkt.
 */
final class AccountAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $loginUrl = $this->urlGenerator->generate('auth.login');

        return new RedirectResponse($loginUrl);
    }

    public function supports(Request $request): ?bool
    {
        return 'auth.login' === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $input = $request->request->all();
        $identifier = is_string($input['username'] ?? null) ? trim($input['username']) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        $csrfToken = is_string($input['_csrf_token'] ?? null) ? $input['_csrf_token'] : '';

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, mb_substr($identifier, 0, 255));
        if ($identifier === '' || strlen($identifier) > 255 || $password === '' || strlen($password) > 4096) {
            throw new BadCredentialsException();
        }

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

        // A fixed internal destination also ignores visitor-supplied return URLs.
        return new RedirectResponse($this->urlGenerator->generate('account.home'), Response::HTTP_SEE_OTHER);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception instanceof TooManyLoginAttemptsAuthenticationException
            ? 'Je hebt te vaak geprobeerd in te loggen. Probeer het later opnieuw.'
            : 'Ongeldige gebruikersnaam/e-mail of wachtwoord.';
        $request->getSession()->getFlashBag()->set('error', [$message]);

        return new RedirectResponse($this->urlGenerator->generate('auth.login'), Response::HTTP_SEE_OTHER);
    }
}
