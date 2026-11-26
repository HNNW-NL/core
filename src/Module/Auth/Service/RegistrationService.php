<?php

namespace App\Module\Auth\Service;

use App\Entity\Account\{Account, Profile};
use App\Entity\Auth\VerifyEmailToken;
use App\Entity\Common\Status;
use App\Module\Auth\Exception\RegistrationValidationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $router,
        #[Autowire('%env(DEFAULT_URI)%')] private readonly string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')] private readonly string $sender,
    ) {
    }

    /** @param array{full_name: string, username: string, email: string} $data */
    public function register(array $data, #[\SensitiveParameter] string $password, #[\SensitiveParameter] string $confirmation, bool $terms): Account
    {
        $data = array_map(trim(...), $data);
        $data['email'] = strtolower($data['email']);
        $errors = [];
        $constraints = [
            'full_name' => [new Assert\NotBlank(message: 'Vul je naam in.'), new Assert\Length(max: 100, maxMessage: 'Gebruik maximaal 100 tekens.')],
            'username' => [new Assert\NotBlank(message: 'Kies een gebruikersnaam.'), new Assert\Length(min: 3, max: 30, minMessage: 'Gebruik minimaal 3 tekens.', maxMessage: 'Gebruik maximaal 30 tekens.')],
            'email' => [new Assert\NotBlank(message: 'Vul je e-mailadres in.'), new Assert\Email(message: 'Voer een geldig e-mailadres in.'), new Assert\Length(max: 254, maxMessage: 'Je e-mailadres is te lang.')],
        ];
        foreach ($constraints as $field => $rules) {
            foreach ($this->validator->validate($data[$field], $rules) as $violation) {
                $errors[$field] ??= $violation->getMessage();
            }
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Gebruik minimaal 8 tekens voor je wachtwoord.';
        } elseif (strlen($password) > 4096) {
            $errors['password'] = 'Je wachtwoord is te lang.';
        }
        if ($password !== $confirmation || $confirmation === '') {
            $errors['confirm_password'] = 'De wachtwoorden komen niet overeen.';
        }
        if (!$terms) {
            $errors['terms'] = 'Ga akkoord met de voorwaarden en privacyverklaring.';
        }
        if ($errors !== []) {
            throw new RegistrationValidationException($errors);
        }

        $existingEmail = $this->entityManager->createQueryBuilder()->select('account.id')->from(Account::class, 'account')
            ->where('LOWER(account.email) = :email')->setParameter('email', $data['email'])
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($existingEmail !== null) {
            $errors['email'] = 'Dit e-mailadres is al in gebruik.';
        }
        if ($this->entityManager->getRepository(Account::class)->findOneBy(['username' => $data['username']]) !== null) {
            $errors['username'] = 'Deze gebruikersnaam is al in gebruik.';
        }
        if ($errors !== []) {
            throw new RegistrationValidationException($errors);
        }

        return $this->entityManager->wrapInTransaction(function () use ($data, $password): Account {
            $status = $this->entityManager->getRepository(Status::class)->findOneBy(['scope' => 'account', 'name' => 'active']);
            if ($status === null) {
                $status = (new Status())->setScope('account')->setName('active')->setColourHex('#10B981');
                $this->entityManager->persist($status);
            }
            $account = (new Account())->setUsername($data['username'])->setEmail($data['email'])->setStatus($status);
            $account->setPasswordHash($this->passwordHasher->hashPassword($account, $password));
            [$firstName, $lastName] = array_pad(explode(' ', $data['full_name'], 2), 2, '');
            $profile = (new Profile())->setAccount($account)->setFirstName($firstName)->setLastName($lastName)
                ->setDisplayName($data['full_name'])->setAvatarUrl('')->setPoints(0);
            $account->setProfile($profile);
            $plainToken = bin2hex(random_bytes(32));
            $verification = (new VerifyEmailToken())->setAccount($account)->setEmailToVerify($data['email'])
                ->setTokenHash(password_hash($plainToken, PASSWORD_DEFAULT));
            foreach ([$account, $profile, $verification] as $entity) {
                $this->entityManager->persist($entity);
            }
            $this->entityManager->flush();

            $originalContext = $this->router->getContext();
            $this->router->setContext(RequestContext::fromUri($this->baseUrl));
            try {
                $link = $this->router->generate('auth.verifyEmail', ['token' => $plainToken, 'email' => $data['email']], UrlGeneratorInterface::ABSOLUTE_URL);
            } finally {
                $this->router->setContext($originalContext);
            }
            $this->mailer->send((new Email())->from($this->sender)->to($data['email'])->subject('Bevestig je e-mailadres')
                ->text("Welkom bij HNNW. Bevestig je e-mailadres met deze eenmalige link:\n\n".$link."\n\nHeb je dit account niet aangemaakt? Dan kun je deze e-mail negeren."));

            return $account;
        });
    }

    public function verifyEmail(#[\SensitiveParameter] string $plainToken, string $email): bool
    {
        if (!preg_match('/\A[0-9a-f]{64}\z/', $plainToken) || strlen($email) > 254) {
            return false;
        }
        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['email' => $email, 'deletedAt' => null]);
        if (!$account instanceof Account) {
            return false;
        }

        return $this->entityManager->wrapInTransaction(function () use ($account, $plainToken, $email): bool {
            $this->entityManager->lock($account, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($account);
            if ($account->getDeletedAt() !== null || $account->getEmail() !== $email) {
                return false;
            }
            $tokens = $this->entityManager->getRepository(VerifyEmailToken::class)->findBy(['account' => $account, 'isUsed' => false]);
            foreach ($tokens as $candidate) {
                $this->entityManager->refresh($candidate);
                if (!$candidate->isUsed() && $candidate->getExpiresAt() > new \DateTimeImmutable()
                    && $candidate->getEmailToVerify() === $email && password_verify($plainToken, $candidate->getTokenHash() ?? '')) {
                    $account->verifyEmail();
                    foreach ($tokens as $token) {
                        $token->markAsUsed();
                    }

                    return true;
                }
            }

            return false;
        });
    }
}
