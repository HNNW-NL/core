<?php

namespace App\Tests\Form\AccountCentre;

use App\Form\AccountCentre\ModifyAccountType;
use App\Module\AccountCentre\DTO\ModifyAccountDTO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

class ModifyAccountTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        // symfony's TypeTestCase maakt zelf een mock-dispatcher en phpunit 13 geeft een melding bij een mock zonder verwachtingen; een stub (lege vulling) is hier genoeg
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        // de dto heeft constraints (NotBlank en Email op e-mail), daarom een echte validator die de attributen leest en geen mock
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        // het formulier zet de optie csrf_message, die bestaat alleen als de csrf-extensie geladen is; de controle zelf zetten we per test uit
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);

        return [
            new ValidatorExtension($validator),
            new CsrfExtension($csrfTokenManager),
        ];
    }

    public function testShouldFillDtoWithValidData(): void
    {
        $dto = new ModifyAccountDTO();
        $form = $this->factory->create(ModifyAccountType::class, $dto, ['csrf_protection' => false]);

        $form->submit([
            'username' => 'TestUser',
            'email' => 'test@voorbeeld.nl',
            'firstName' => 'Test',
            'lastName' => 'User',
            'displayName' => 'Tester',
            'avatarUrl' => 'https://voorbeeld.nl/avatar.png',
            'location' => 'Amsterdam',
            'description' => 'Iets over mij',
            'language' => 'nl-NL',
            'theme' => 'light',
            'profileVisibility' => 'public',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('TestUser', $dto->username);
        $this->assertSame('test@voorbeeld.nl', $dto->email);
        $this->assertSame('Test', $dto->firstName);
        $this->assertSame('User', $dto->lastName);
        $this->assertSame('Tester', $dto->displayName);
        $this->assertSame('https://voorbeeld.nl/avatar.png', $dto->avatarUrl);
        $this->assertSame('Amsterdam', $dto->location);
        $this->assertSame('Iets over mij', $dto->description);
        $this->assertSame('nl-NL', $dto->language);
        $this->assertSame('light', $dto->theme);
        $this->assertSame('public', $dto->profileVisibility);
    }

    public function testWhenEmailIsInvalidShouldGiveError(): void
    {
        $dto = new ModifyAccountDTO();
        $form = $this->factory->create(ModifyAccountType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData(['email' => 'geen-email']));

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('email')->getErrors());
        $this->assertSame('Dit is geen geldig e-mailadres.', $form->get('email')->getErrors()[0]->getMessage());
    }

    public function testWhenEmailIsEmptyShouldGiveError(): void
    {
        $dto = new ModifyAccountDTO();
        $form = $this->factory->create(ModifyAccountType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData(['email' => '']));

        $this->assertFalse($form->isValid());
        $this->assertSame('Vul een e-mailadres in.', $form->get('email')->getErrors()[0]->getMessage());
    }

    public function testWhenLanguageIsTooLongShouldGiveError(): void
    {
        $dto = new ModifyAccountDTO();
        $form = $this->factory->create(ModifyAccountType::class, $dto, ['csrf_protection' => false]);

        // de kolom in de database is 5 tekens lang
        $form->submit($this->validData(['language' => 'nl-NL-extra']));

        $this->assertFalse($form->isValid());
        $this->assertSame('Een taalcode is maximaal 5 tekens.', $form->get('language')->getErrors()[0]->getMessage());
    }

    // geldige invoer waar een test één veld van kan veranderen
    private function validData(array $anders): array
    {
        $data = [
            'username' => 'TestUser',
            'email' => 'test@voorbeeld.nl',
            'firstName' => 'Test',
            'lastName' => 'User',
            'displayName' => '',
            'avatarUrl' => '',
            'location' => '',
            'description' => '',
            'language' => 'nl-NL',
            'theme' => 'dark',
            'profileVisibility' => 'private',
        ];

        return array_merge($data, $anders);
    }
}
