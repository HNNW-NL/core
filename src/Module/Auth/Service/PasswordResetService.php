<?php

namespace App\Module\Auth\Service;

use App\Entity\Account\Account;
use App\Entity\Auth\ResetPasswordToken;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class PasswordResetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $sender,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function sendResetLink(string $email): void
    {
        $normalisedEmail = strtolower(trim($email));
        $account = $this->entityManager->createQueryBuilder()
            ->select('account')
            ->from(Account::class, 'account')
            ->where('LOWER(account.email) = :email')
            ->andWhere('account.deletedAt IS NULL')
            ->setParameter('email', $normalisedEmail)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$account instanceof Account) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($account, $normalisedEmail): void {
            // Serialize requests for this account before replacing its recovery links.
            // Refresh separately because Account has nullable one-to-one relations.
            $this->entityManager->lock($account, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($account);
            if ($account->getDeletedAt() !== null || strtolower($account->getEmail()) !== $normalisedEmail) {
                return;
            }

            $previousTokens = $this->entityManager->getRepository(ResetPasswordToken::class)->findBy([
                'account' => $account,
                'isUsed' => false,
            ]);
            foreach ($previousTokens as $previousToken) {
                $previousToken->markAsUsed();
            }

            $plainToken = bin2hex(random_bytes(32));
            $token = (new ResetPasswordToken())
                ->setAccount($account)
                ->setTokenHash(password_hash($plainToken, PASSWORD_DEFAULT));
            $this->entityManager->persist($token);
            $this->entityManager->flush();

            $link = $this->generateLink($plainToken, $account->getEmail());
            $this->mailer->send((new Email())
                ->from($this->sender)
                ->to($account->getEmail())
                ->subject('Wachtwoord resetten')
                ->text("Je hebt een herstellink aangevraagd voor je HNNW-account.\n\n".$link."\n\nDeze link is 20 minuten geldig en kan eenmalig worden gebruikt. Heb je dit niet aangevraagd? Dan kun je deze e-mail negeren."));
        });
    }

    private function generateLink(#[\SensitiveParameter] string $plainToken, string $email): string
    {
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

    public function findValidToken(#[\SensitiveParameter] string $plainToken, string $email): ?ResetPasswordToken
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/', $plainToken) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['email' => $email, 'deletedAt' => null]);
        if (!$account instanceof Account) {
            return null;
        }
        foreach ($this->entityManager->getRepository(ResetPasswordToken::class)->findBy(['account' => $account, 'isUsed' => false]) as $candidate) {
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
            $this->entityManager->lock($account, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($account);
            $this->entityManager->lock($token, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($token);
            if ($account->getDeletedAt() !== null || $account->getEmail() !== $email
                || !$token->getAccount()->getId()->equals($account->getId()) || !$this->isValid($token, $plainToken)) {
                return false;
            }
            $account->setPasswordHash($passwordHash);
            foreach ($this->entityManager->getRepository(ResetPasswordToken::class)->findBy(['account' => $account, 'isUsed' => false]) as $otherToken) {
                $otherToken->markAsUsed();
            }

            return true;
        });
    }

    private function isValid(ResetPasswordToken $token, #[\SensitiveParameter] string $plainToken): bool
    {
        return !$token->isUsed() && $token->getExpiresAt() > new \DateTimeImmutable()
            && password_verify($plainToken, $token->getTokenHash() ?? '');
    }
}
