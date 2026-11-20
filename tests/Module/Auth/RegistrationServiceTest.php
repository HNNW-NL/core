<?php

namespace App\Tests\Module\Auth;

use App\Entity\Account\Account;
use App\Entity\Account\Profile;
use App\Entity\Auth\VerifyEmailToken;
use App\Entity\Common\Status;
use App\Module\Auth\Exception\RegistrationValidationException;
use App\Module\Auth\Service\RegistrationService;
use App\Repository\Account\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Validator\Validation;

final class RegistrationServiceTest extends TestCase
{
    public static function invalidInput(): iterable
    {
        yield 'missing name' => ['full_name', '', 'full_name'];
        yield 'long name' => ['full_name', str_repeat('a', 101), 'full_name'];
        yield 'short username' => ['username', 'ab', 'username'];
        yield 'long username' => ['username', str_repeat('a', 31), 'username'];
        yield 'invalid email' => ['email', 'not-an-email', 'email'];
        yield 'long email' => ['email', str_repeat('x', 250).'@example.test', 'email'];
        yield 'short password' => ['password', 'short', 'password'];
        yield 'short unicode password' => ['password', 'ééééééé', 'password'];
        yield 'long password' => ['password', str_repeat('x', 4097), 'password'];
        yield 'mismatched passwords' => ['confirmation', 'Different-123', 'confirm_password'];
        yield 'missing terms' => ['terms', false, 'terms'];
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputNeverQueriesOrWritesDatabase(string $field, string|bool $value, string $error): void
    {
        $input = ['full_name' => 'Test Persoon', 'username' => 'test-person', 'email' => 'test@example.test', 'password' => 'Test-password-123', 'confirmation' => 'Test-password-123', 'terms' => true];
        $input[$field] = $value;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('wrapInTransaction');
        $accounts = $this->createMock(AccountRepository::class);
        $accounts->expects(self::never())->method('findOneBy');
        $accounts->expects(self::never())->method('findOneByNormalisedEmail');
        try {
            $this->service($em, $accounts)->register(array_intersect_key($input, array_flip(['full_name', 'username', 'email'])), $input['password'], $input['confirmation'], $input['terms']);
            self::fail('Invalid registration was accepted.');
        } catch (RegistrationValidationException $exception) {
            self::assertArrayHasKey($error, $exception->errors);
        }
    }

    public function testExistingNormalisedEmailIsRejectedBeforeWriting(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('wrapInTransaction');
        $accounts = $this->createMock(AccountRepository::class);
        $accounts->expects(self::once())->method('findOneBy')->with(['username' => 'test-person'])->willReturn(null);
        $accounts->expects(self::once())->method('findOneByNormalisedEmail')->with('test@example.test')->willReturn(new Account());
        try {
            $this->service($em, $accounts)->register(['full_name' => 'Test Persoon', 'username' => 'test-person', 'email' => ' TEST@Example.Test '], 'Test-password-123', 'Test-password-123', true);
            self::fail('Duplicate email was accepted.');
        } catch (RegistrationValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors);
        }
    }

    public function testValidRegistrationUsesHasherAndBindsAccountProfileAndVerification(): void
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('wrapInTransaction')->willReturnCallback(static fn (callable $operation) => $operation());
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void { $persisted[] = $entity; });
        $statuses = $this->createStub(EntityRepository::class);
        $statuses->method('findOneBy')->willReturn((new Status())->setName('active')->setScope('account'));
        $em->method('getRepository')->willReturn($statuses);
        $accounts = $this->createStub(AccountRepository::class);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function ($mail): void {
            self::assertSame('test@example.test', $mail->getTo()[0]->getAddress());
            self::assertStringContainsString('https://hnnw.example.test/auth/verify-email/', $mail->getTextBody());
            self::assertStringNotContainsString('Test-password-123', $mail->getTextBody());
        });
        $account = $this->service($em, $accounts, $mailer)->register(['full_name' => 'Test Persoon', 'username' => 'test-person', 'email' => ' TEST@Example.Test '], 'Test-password-123', 'Test-password-123', true);
        self::assertSame('test@example.test', $account->getEmail());
        self::assertNull($account->getEmailVerifiedAt());
        self::assertSame(['ROLE_USER'], $account->getRoles());
        self::assertTrue(password_verify('Test-password-123', $account->getPasswordHash()));
        self::assertCount(3, $persisted);
        self::assertInstanceOf(Profile::class, $persisted[1]);
        self::assertSame($account, $persisted[1]->getAccount());
        self::assertSame('Test Persoon', $persisted[1]->getDisplayName());
        self::assertInstanceOf(VerifyEmailToken::class, $persisted[2]);
        self::assertSame($account, $persisted[2]->getAccount());
        self::assertSame($account->getEmail(), $persisted[2]->getEmailToVerify());
    }

    private function service(EntityManagerInterface $em, AccountRepository $accounts, ?MailerInterface $mailer = null): RegistrationService
    {
        $routes = new RouteCollection();
        $routes->add('auth.verifyEmail', new Route('/auth/verify-email/{token}/{email}'));

        return new RegistrationService($em, $accounts, Validation::createValidator(),
            new UserPasswordHasher(new PasswordHasherFactory([Account::class => new NativePasswordHasher(cost: 4)])),
            $mailer ?? $this->createStub(MailerInterface::class), new UrlGenerator($routes, new RequestContext()),
            'https://hnnw.example.test', 'HNNW <no-reply@example.test>');
    }
}
