<?php

namespace App\Tests\Form\AccountCentre;

use App\Form\AccountCentre\ExperienceType;
use App\Module\AccountCentre\DTO\ExperienceDTO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validation;

class ExperienceTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        // symfony's TypeTestCase maakt zelf een mock-dispatcher en phpunit 13 geeft een melding bij een mock zonder verwachtingen; een stub (lege vulling) is hier genoeg
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        // de NotBlank-regels staan hier op de velden van het formulier, ook daarvoor is een echte validator nodig en geen mock
        $validator = Validation::createValidator();

        // het formulier zet de optie csrf_message, die bestaat alleen als de csrf-extensie geladen is; de controle zelf zetten we per test uit
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);

        return [
            new ValidatorExtension($validator),
            new CsrfExtension($csrfTokenManager),
        ];
    }

    public function testShouldFillDtoWithValidData(): void
    {
        $dto = new ExperienceDTO();
        $form = $this->factory->create(ExperienceType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData([
            'isCurrent' => '1',
            'endDate' => '',
        ]));

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Testfunctie', $dto->jobTitle);
        $this->assertSame('Testbedrijf', $dto->organisationName);
        $this->assertSame('vast', $dto->employmentType);
        $this->assertSame('Een korte beschrijving', $dto->description);
        $this->assertSame('Amsterdam', $dto->location);
        $this->assertSame('remote', $dto->locationType);
        $this->assertSame('Developer', $dto->profileHeadline);
        $this->assertSame('linkedin', $dto->vacancySource);
        $this->assertSame('PHP, Symfony', $dto->skills);
        $this->assertTrue($dto->isCurrent);
        // het datumveld komt als tekst binnen en wordt een DateTimeImmutable in de dto
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->startDate);
        $this->assertSame('2024-01-01', $dto->startDate->format('Y-m-d'));
        $this->assertNull($dto->endDate);
    }

    public function testWhenEndDateIsFilledShouldFillDtoWithEndDate(): void
    {
        $dto = new ExperienceDTO();
        $form = $this->factory->create(ExperienceType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData(['endDate' => '2024-12-31']));

        $this->assertTrue($form->isValid());
        $this->assertFalse($dto->isCurrent);
        $this->assertSame('2024-12-31', $dto->endDate->format('Y-m-d'));
    }

    public function testWhenJobTitleIsEmptyShouldGiveError(): void
    {
        $dto = new ExperienceDTO();
        $form = $this->factory->create(ExperienceType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData(['jobTitle' => '']));

        $this->assertFalse($form->isValid());
        $this->assertSame('Vul een functietitel in.', $form->get('jobTitle')->getErrors()[0]->getMessage());
    }

    public function testWhenOrganisationNameIsEmptyShouldGiveError(): void
    {
        $dto = new ExperienceDTO();
        $form = $this->factory->create(ExperienceType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData(['organisationName' => '']));

        $this->assertFalse($form->isValid());
        $this->assertSame('Vul een bedrijf of organisatie in.', $form->get('organisationName')->getErrors()[0]->getMessage());
    }

    public function testWhenStartDateIsEmptyShouldGiveError(): void
    {
        $dto = new ExperienceDTO();
        $form = $this->factory->create(ExperienceType::class, $dto, ['csrf_protection' => false]);

        $form->submit($this->validData(['startDate' => '']));

        $this->assertFalse($form->isValid());
        $this->assertSame('Vul een startdatum in.', $form->get('startDate')->getErrors()[0]->getMessage());
    }

    // geldige invoer waar een test één veld van kan veranderen
    private function validData(array $anders): array
    {
        $data = [
            'jobTitle' => 'Testfunctie',
            'employmentType' => 'vast',
            'organisationName' => 'Testbedrijf',
            'startDate' => '2024-01-01',
            'endDate' => '',
            'location' => 'Amsterdam',
            'locationType' => 'remote',
            'profileHeadline' => 'Developer',
            'vacancySource' => 'linkedin',
            'skills' => 'PHP, Symfony',
            'description' => 'Een korte beschrijving',
        ];

        return array_merge($data, $anders);
    }
}
