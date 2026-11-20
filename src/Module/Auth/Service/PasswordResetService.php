<?php

namespace App\Module\Auth\Service;

use App\Entity\Account\Account;
use App\Entity\Auth\ResetPasswordToken;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class PasswordResetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $sender,
    ) {
    }

    public function sendResetLink(string $email): void
    {
        $account = $this->entityManager->getRepository(Account::class)->findOneBy([
            'email' => $email,
            'deletedAt' => null,
        ]);

        if (!$account instanceof Account) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($account, $email): void {
            // Issuing and redeeming links always lock the account first.
            $this->lockAccount($account);
            if ($account->getDeletedAt() !== null || $account->getEmail() !== $email) {
                return;
            }

            $this->invalidateTokens($account);

            $plainToken = bin2hex(random_bytes(32));
            $token = (new ResetPasswordToken())
                ->setAccount($account)
                ->setTokenHash(password_hash($plainToken, PASSWORD_DEFAULT));
            $this->entityManager->persist($token);
            $this->entityManager->flush();

            $resetLink = $this->generateResetLink($plainToken, $email);

            $this->mailer->send((new TemplatedEmail())
                ->from($this->sender)
                ->to($email)
                ->subject('Stel je nieuwe HNNW-wachtwoord in')
                ->text("Je hebt een herstellink aangevraagd voor je HNNW-account.\n\n".$resetLink."\n\nDeze link is 20 minuten geldig en kan eenmalig worden gebruikt. Heb je dit niet aangevraagd? Dan kun je deze e-mail negeren.")
                ->htmlTemplate('emails/reset-password.html.twig')
                ->context(['reset_link' => $resetLink]));
        });
    }

    public function findValidToken(#[\SensitiveParameter] string $plainToken, string $email): ?ResetPasswordToken
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/', $plainToken)) {
            return null;
        }

        $account = $this->entityManager->getRepository(Account::class)->findOneBy([
            'email' => $email,
            'deletedAt' => null,
        ]);
        if (!$account instanceof Account) {
            return null;
        }

        foreach ($this->unusedTokens($account) as $candidate) {
            if ($this->isValid($candidate, $plainToken)) {
                return $candidate;
            }
        }

        return null;
    }

    public function resetPassword(#[\SensitiveParameter] string $plainToken, string $email, #[\SensitiveParameter] string $password): bool
    {
        if (mb_strlen($password) < 8 || strlen($password) > 4096) {
            throw new \InvalidArgumentException('Invalid password length.');
        }

        $token = $this->findValidToken($plainToken, $email);
        if ($token === null) {
            return false;
        }

        $account = $token->getAccount();
        $passwordHash = $this->passwordHasher->hashPassword($account, $password);

        return $this->entityManager->wrapInTransaction(function () use ($account, $token, $plainToken, $email, $passwordHash): bool {
            // Refresh after acquiring locks: a concurrent request may have used
            // or replaced the token since the form was opened.
            $this->lockAccount($account);
            $this->entityManager->lock($token, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($token);

            if ($account->getDeletedAt() !== null || $account->getEmail() !== $email
                || (string) $token->getAccount()?->getId() !== (string) $account->getId()
                || !$this->isValid($token, $plainToken)
            ) {
                return false;
            }

            $account->setPasswordHash($passwordHash);
            $this->invalidateTokens($account);

            // wrapInTransaction flushes both changes and rolls both back on failure.
            return true;
        });
    }

    private function isValid(ResetPasswordToken $token, #[\SensitiveParameter] string $plainToken): bool
    {
        return !$token->isUsed()
            && $token->getExpiresAt() > new \DateTimeImmutable()
            && password_verify($plainToken, $token->getTokenHash() ?? '');
    }

    /** @return list<ResetPasswordToken> */
    private function unusedTokens(Account $account): array
    {
        return $this->entityManager->getRepository(ResetPasswordToken::class)->findBy([
            'account' => $account,
            'isUsed' => false,
        ]);
    }

    private function invalidateTokens(Account $account): void
    {
        foreach ($this->unusedTokens($account) as $token) {
            $token->markAsUsed();
        }
    }

    private function lockAccount(Account $account): void
    {
        // Lock separately: refreshing Account loads nullable one-to-one joins,
        // which PostgreSQL cannot include in a SELECT ... FOR UPDATE.
        $this->entityManager->lock($account, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->refresh($account);
    }

    private function generateResetLink(#[\SensitiveParameter] string $plainToken, string $email): string
    {
        // Use configured infrastructure, never a visitor-supplied Host header.
        $originalContext = $this->router->getContext();
        $this->router->setContext(RequestContext::fromUri($this->baseUrl));
        try {
            return $this->router->generate('auth.resetPassword', [
                'token' => $plainToken,
                'email' => $email,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            $this->router->setContext($originalContext);
        }
    }
}
