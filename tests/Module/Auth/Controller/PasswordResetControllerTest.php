<?php

namespace App\Tests\Module\Auth\Controller;

use App\Entity\Account\Account;
use App\Entity\Auth\ResetPasswordToken;
use App\Kernel;
use App\Module\Auth\Service\PasswordResetService;
use App\Security\AccountAuthenticator;
use App\Security\AccountUserProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetControllerTest extends WebTestCase
{
    private const EMAIL = 'reset-user@example.test';
    private const OLD_PASSWORD = 'Old-password-123';
    private const NEW_PASSWORD = 'New-password-456';
    private const CONFIRMATION = 'Als er een account met dit e-mailadres bestaat, ontvang je een e-mail met een herstellink.';

    private Account $account;
    /** @var list<ResetPasswordToken> */
    private array $tokens = [];
    /** @var list<TemplatedEmail> */
    private array $messages = [];
    private UserPasswordHasherInterface $hasher;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public static function emailAddresses(): iterable
    {
        yield 'known' => [self::EMAIL, 1];
        yield 'unknown' => ['unknown@example.test', 0];
    }

    #[DataProvider('emailAddresses')]
    public function testEmailExistenceDoesNotChangeVisibleConfirmation(string $email, int $mailCount): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/auth/forgot-password');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('button[type="submit"]', 'Herstellink aanvragen');
        $client->submit($crawler->selectButton('Herstellink aanvragen')->form(['email' => $email]));

        self::assertResponseRedirects('/auth/forgot-password', 303);
        self::assertEmailCount($mailCount);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('[role="status"]', self::CONFIRMATION);
        self::assertSelectorExists('a[href="/auth/login"]');
        self::assertCount($mailCount, $this->messages);
    }

    public static function invalidEmails(): iterable
    {
        yield 'empty' => [''];
        yield 'malformed' => ['not-an-email'];
        yield 'markup' => ['<script>alert(1)</script>'];
        yield 'too long' => [str_repeat('a', 245).'@example.test'];
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmailHasAnEscapedFieldErrorAndDoesNotSendMail(string $email): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/auth/forgot-password');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $crawler = $client->request('POST', '/auth/forgot-password', ['email' => $email, '_csrf_token' => $csrf]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextSame('#email-error', 'Voer een geldig e-mailadres in.');
        self::assertSame($email, $crawler->filter('#email')->attr('value'));
        self::assertSelectorExists('#email[aria-invalid="true"]');
        self::assertStringNotContainsString('<script>alert(1)</script>', $client->getResponse()->getContent());
        self::assertCount(0, $this->messages);
        self::assertCount(0, $this->tokens);
    }

    public function testBothMutationsRequireCsrfAndPasswordsAreNeverRedisplayed(): void
    {
        $client = $this->browser();
        $client->request('POST', '/auth/forgot-password', ['email' => self::EMAIL]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Het formulier is verlopen.');
        self::assertCount(0, $this->messages);

        $plainToken = $this->token();
        $client->request('POST', $this->resetPath($plainToken), [
            'password' => self::NEW_PASSWORD, 'confirm_password' => self::NEW_PASSWORD,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Het formulier is verlopen.');
        self::assertSelectorNotExists('input[type="password"][value]');
        self::assertStringNotContainsString(self::NEW_PASSWORD, $client->getResponse()->getContent());
        self::assertTrue($this->hasher->isPasswordValid($this->account, self::OLD_PASSWORD));
        self::assertFalse($this->tokens[0]->isUsed());
    }

    public function testMailedLinkResetsPasswordOnceAndUsesTrustedBaseUrl(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', 'https://untrusted.example/auth/forgot-password');
        $client->submit($crawler->selectButton('Herstellink aanvragen')->form(['email' => self::EMAIL]));

        self::assertCount(1, $this->messages);
        $mail = $this->messages[0];
        preg_match('~https://[^\s]+~', $mail->getTextBody(), $matches);
        $link = $matches[0];
        self::assertStringStartsWith('https://hnnw.example.test/auth/reset-password/', $link);
        self::assertStringNotContainsString('untrusted.example', $link);
        self::assertSame(self::EMAIL, $mail->getTo()[0]->getAddress());
        self::assertStringContainsString($link, $mail->getTextBody());
        $html = $mail->getHtmlBody();
        self::assertStringContainsString('Nieuw wachtwoord instellen', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertSame(1200, $this->tokens[0]->getExpiresAt()->getTimestamp() - $this->tokens[0]->getCreatedAt()->getTimestamp());

        $crawler = $client->request('GET', $link);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertStringContainsString('no-store', $client->getResponse()->headers->get('Cache-Control'));
        $client->submit($crawler->selectButton('Wachtwoord opslaan')->form([
            'password' => self::NEW_PASSWORD, 'confirm_password' => self::NEW_PASSWORD,
        ]));

        self::assertResponseRedirects('/auth/login', 303);
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertTrue($this->hasher->isPasswordValid($this->account, self::NEW_PASSWORD));
        self::assertFalse($this->hasher->isPasswordValid($this->account, self::OLD_PASSWORD));
        self::assertTrue($this->tokens[0]->isUsed());
        $client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'Je wachtwoord is gewijzigd.');
        $client->request('GET', $link);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorTextContains('[role="alert"]', 'ongeldig of verlopen');
        self::assertSelectorExists('a[href="/auth/forgot-password"]');
        self::assertSelectorNotExists('#reset-form');

        $crawler = $client->request('GET', '/auth/login');
        $client->submit($crawler->selectButton('Log in')->form([
            'username' => self::EMAIL, 'password' => self::OLD_PASSWORD,
        ]));
        self::assertStringStartsWith('/auth/login', $client->getResponse()->headers->get('Location'));
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Ongeldige gebruikersnaam/e-mail of wachtwoord.');
        $client->submit($crawler->selectButton('Log in')->form([
            'username' => self::EMAIL, 'password' => self::NEW_PASSWORD,
        ]));
        self::assertResponseRedirects('/');
    }

    public static function invalidPasswords(): iterable
    {
        yield 'empty' => ['', '', 'password-error'];
        yield 'too short' => ['short', 'short', 'password-error'];
        yield 'seven unicode characters' => ['ééééééé', 'ééééééé', 'password-error'];
        yield 'too long' => [str_repeat('x', 4097), str_repeat('x', 4097), 'password-error'];
        yield 'mismatch' => [self::NEW_PASSWORD, 'Something-else-789', 'confirm_password-error'];
    }

    #[DataProvider('invalidPasswords')]
    public function testInvalidPasswordDoesNotChangeHashOrConsumeToken(string $password, string $confirmation, string $error): void
    {
        $client = $this->browser();
        $path = $this->resetPath($this->token());
        $crawler = $client->request('GET', $path);
        $client->request('POST', $path, [
            '_csrf_token' => $crawler->filter('input[name="_csrf_token"]')->attr('value'),
            'password' => $password, 'confirm_password' => $confirmation,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#'.$error.':not(.visually-hidden)');
        self::assertSelectorNotExists('input[type="password"][value]');
        self::assertTrue($this->hasher->isPasswordValid($this->account, self::OLD_PASSWORD));
        self::assertFalse($this->tokens[0]->isUsed());
    }

    public static function invalidLinks(): iterable
    {
        foreach (['expired', 'used', 'modified', 'other-account', 'unknown-account', 'malformed', 'suffix'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('invalidLinks')]
    public function testInvalidLinksNeverShowPasswordFormOrChangePassword(string $state): void
    {
        $client = $this->browser();
        $plainToken = $this->token();
        $email = self::EMAIL;
        if ($state === 'expired') {
            (new \ReflectionProperty(ResetPasswordToken::class, 'expiresAt'))->setValue($this->tokens[0], new \DateTimeImmutable('-1 second'));
        } elseif ($state === 'used') {
            $this->tokens[0]->markAsUsed();
        } elseif ($state === 'modified') {
            $plainToken = str_repeat('f', 64);
        } elseif ($state === 'other-account') {
            $email = 'other@example.test';
        } elseif ($state === 'unknown-account') {
            $email = 'unknown@example.test';
        } elseif ($state === 'malformed') {
            $plainToken = 'invalid';
        } elseif ($state === 'suffix') {
            $plainToken .= str_repeat('a', 64);
        }

        foreach (['GET', 'POST'] as $method) {
            $client->request($method, $this->resetPath($plainToken, $email), [
                'password' => self::NEW_PASSWORD, 'confirm_password' => self::NEW_PASSWORD,
            ]);
            self::assertResponseStatusCodeSame(400);
            self::assertSelectorTextContains('[role="alert"]', 'ongeldig of verlopen');
            self::assertSelectorNotExists('form');
        }
        self::assertTrue($this->hasher->isPasswordValid($this->account, self::OLD_PASSWORD));
    }

    public function testMailFailureKeepsTheSameNeutralResponse(): void
    {
        $client = $this->browser(mailFails: true);
        $crawler = $client->request('GET', '/auth/forgot-password');
        $client->submit($crawler->selectButton('Herstellink aanvragen')->form(['email' => self::EMAIL]));
        self::assertResponseRedirects('/auth/forgot-password', 303);
        $client->followRedirect();
        self::assertSelectorTextSame('[role="status"]', self::CONFIRMATION);
        self::assertStringNotContainsString('Transport', $client->getResponse()->getContent());
    }

    private function browser(bool $mailFails = false): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->catchExceptions(false);
        $this->hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $this->account = (new Account())->setEmail(self::EMAIL);
        $this->account->setPasswordHash($this->hasher->hashPassword($this->account, self::OLD_PASSWORD));
        $other = (new Account())->setEmail('other@example.test');

        $accounts = $this->createStub(EntityRepository::class);
        $accounts->method('findOneBy')->willReturnCallback(fn (array $criteria): ?Account => match ($criteria['email'] ?? '') {
            self::EMAIL => $this->account,
            'other@example.test' => $other,
            default => null,
        });
        $accounts->method('find')->willReturn($this->account);
        $tokens = $this->createStub(EntityRepository::class);
        $tokens->method('findBy')->willReturnCallback(fn (array $criteria): array => array_values(array_filter(
            $this->tokens, static fn (ResetPasswordToken $token): bool => $token->getAccount() === $criteria['account'] && !$token->isUsed(),
        )));
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnMap([[Account::class, $accounts], [ResetPasswordToken::class, $tokens]]);
        $em->method('wrapInTransaction')->willReturnCallback(static fn (callable $operation): mixed => $operation($em));
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof ResetPasswordToken) {
                $entity->onCreate();
                $this->tokens[] = $entity;
            }
        });
        $testMailer = new Mailer(new NullTransport(self::getContainer()->get('event_dispatcher')));
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (TemplatedEmail $mail) use ($mailFails, $testMailer): void {
            if ($mailFails) {
                throw new TransportException('Simulated mail failure');
            }
            $testMailer->send($mail);
            $messages = self::getMailerMessages();
            $this->messages[] = end($messages);
        });
        $router = self::getContainer()->get(UrlGeneratorInterface::class);
        self::getContainer()->set(AccountUserProvider::class, new AccountUserProvider($em));
        self::getContainer()->set(AccountAuthenticator::class, new AccountAuthenticator($router, $em));
        self::getContainer()->set(PasswordResetService::class, new PasswordResetService(
            $em, $this->hasher, $mailer, $router, 'https://hnnw.example.test', 'HNNW <no-reply@example.test>',
        ));

        return $client;
    }

    private function token(): string
    {
        $plain = bin2hex(random_bytes(32));
        $token = (new ResetPasswordToken())->setAccount($this->account)->setTokenHash(password_hash($plain, PASSWORD_BCRYPT, ['cost' => 4]));
        $token->onCreate();
        $this->tokens[] = $token;

        return $plain;
    }

    private function resetPath(string $token, string $email = self::EMAIL): string
    {
        return '/auth/reset-password/'.$token.'/'.rawurlencode($email);
    }
}
