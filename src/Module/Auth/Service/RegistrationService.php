<?php

namespace App\Module\Auth\Service;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Auth\VerifyEmailToken;
use App\Entity\Common\Status;
use App\Module\Auth\Exception\RegistrationValidationException;
use App\Repository\Account\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AccountRepository $accounts,
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
        $data = [
            'full_name' => trim($data['full_name']),
            'username' => trim($data['username']),
            'email' => strtolower(trim($data['email'])),
        ];
        $errors = [];
        $rules = [
            'full_name' => [new Assert\NotBlank(message: 'Vul je naam in.'), new Assert\Length(max: 100, maxMessage: 'Gebruik maximaal 100 tekens voor je naam.')],
            'username' => [new Assert\NotBlank(message: 'Kies een gebruikersnaam.'), new Assert\Length(min: 3, max: 30, minMessage: 'Gebruik minimaal 3 tekens.', maxMessage: 'Gebruik maximaal 30 tekens.')],
            'email' => [new Assert\NotBlank(message: 'Vul je e-mailadres in.'), new Assert\Email(message: 'Voer een geldig e-mailadres in.'), new Assert\Length(max: 254, maxMessage: 'Je e-mailadres is te lang.')],
        ];
        foreach ($rules as $field => $constraints) {
            foreach ($this->validator->validate($data[$field], $constraints) as $violation) {
                $errors[$field] ??= $violation->getMessage();
            }
        }
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Gebruik minimaal 8 tekens voor je wachtwoord.';
        } elseif (strlen($password) > 4096) {
            $errors['password'] = 'Je wachtwoord is te lang. Kies een korter wachtwoord.';
        }
        if ($password !== $confirmation || $confirmation === '') {
            $errors['confirm_password'] = 'De wachtwoorden komen niet overeen.';
        }
        if (!$terms) {
            $errors['terms'] = 'Ga akkoord met de voorwaarden en privacyverklaring.';
        }
        // Invalid input is rejected before querying or writing the database.
        if ($errors !== []) {
            throw new RegistrationValidationException($errors);
        }
        if ($this->accounts->findOneBy(['username' => $data['username']]) !== null) {
            $errors['username'] = 'Deze gebruikersnaam is al in gebruik.';
        }
        if ($this->accounts->findOneByNormalisedEmail($data['email']) !== null) {
            $errors['email'] = 'Dit e-mailadres is al in gebruik.';
        }
        if ($errors !== []) {
            throw new RegistrationValidationException($errors);
        }

        return $this->entityManager->wrapInTransaction(function () use ($data, $password): Account {
            $status = $this->entityManager->getRepository(Status::class)->findOneBy(['scope' => 'account', 'name' => 'active']);
            if ($status === null) {
                $status = (new Status())->setName('active')->setScope('account')->setColourHex('#10B981');
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

            $context = $this->router->getContext();
            $this->router->setContext(RequestContext::fromUri($this->baseUrl));
            try {
                $link = $this->router->generate('auth.verifyEmail', ['token' => $plainToken, 'email' => $data['email']], UrlGeneratorInterface::ABSOLUTE_URL);
            } finally {
                $this->router->setContext($context);
            }
            $this->mailer->send((new TemplatedEmail())->from($this->sender)->to($data['email'])
                ->subject('Bevestig je e-mailadres')
                ->text("Welkom bij HNNW. Bevestig je e-mailadres met deze eenmalige link:\n\n".$link."\n\nHeb je dit account niet aangemaakt? Dan kun je deze e-mail negeren.")
                ->htmlTemplate('emails/verify-email.html.twig')->context(['verification_link' => $link]));

            return $account;
        });
    }
}
