<?php

namespace App\Module\Auth\Service;

use App\Entity\Account\Account;
use App\Entity\Auth\ResetPasswordToken;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
}
