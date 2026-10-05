<?php

namespace App\Tests\Form\Org;

use App\Form\Org\PackageTaskType;
use App\Module\Org\DTO\PackageTaskFormDTO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class PackageTaskTypeTest extends TypeTestCase
{
    // verzonnen uuid, het formulier geeft hem alleen door en zoekt er niets mee op in de database
    private const WORK_PACKAGE_ID = '01a10c37-444f-7b54-abae-e6ab6815b406';

    // de basisklasse maakt zelf een mock dispatcher zonder verwachtingen en daar geeft phpunit een notice op, een stub is dan netter
    protected function setUp(): void
    {
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    // de velden hebben NotBlank regels in het formulier, zonder de validator extensie worden die niet gecontroleerd
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    public function testShouldBuildFormWithDto(): void
    {
        $dto = new PackageTaskFormDTO(self::WORK_PACKAGE_ID);

        $form = $this->factory->create(PackageTaskType::class, $dto);

        $this->assertSame($dto, $form->getData());
        $this->assertTrue($form->has('workPackageId'));
        $this->assertTrue($form->has('taskTitle'));
        $this->assertTrue($form->has('taskSlug'));
        $this->assertTrue($form->has('taskDescription'));
        $this->assertTrue($form->has('taskDueDate'));
        // de prioriteit zit niet in het formulier, de dto houdt normal als standaard
        $this->assertFalse($form->has('taskPriority'));
        // het verborgen veld krijgt het id van het werkpakket uit de dto
        $this->assertSame(self::WORK_PACKAGE_ID, $form->get('workPackageId')->getData());
    }

    public function testWhenSubmittedShouldFillDto(): void
    {
        $dto = new PackageTaskFormDTO(self::WORK_PACKAGE_ID);
        $form = $this->factory->create(PackageTaskType::class, $dto);

        $form->submit([
            'workPackageId' => self::WORK_PACKAGE_ID,
            'taskTitle' => 'Nieuwe taak',
            'taskSlug' => 'nieuwe-taak',
            'taskDescription' => 'Omschrijving van de taak',
            'taskDueDate' => '2026-12-31',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame(self::WORK_PACKAGE_ID, $dto->workPackageId);
        $this->assertSame('Nieuwe taak', $dto->taskTitle);
        $this->assertSame('nieuwe-taak', $dto->taskSlug);
        $this->assertSame('Omschrijving van de taak', $dto->taskDescription);
        $this->assertSame('2026-12-31', $dto->taskDueDate->format('Y-m-d'));
        $this->assertSame('normal', $dto->taskPriority);
    }

    public function testWhenTaskTitleIsEmptyShouldBeInvalid(): void
    {
        $form = $this->factory->create(PackageTaskType::class, new PackageTaskFormDTO(self::WORK_PACKAGE_ID));

        $form->submit([
            'workPackageId' => self::WORK_PACKAGE_ID,
            'taskTitle' => '',
            'taskSlug' => 'zonder-titel',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('taskTitle')->getErrors());
    }

    public function testWhenTaskTitleIsTooLongShouldBeInvalid(): void
    {
        $form = $this->factory->create(PackageTaskType::class, new PackageTaskFormDTO(self::WORK_PACKAGE_ID));

        // de kolom title is 255 tekens, dus 256 tekens moet een nette fout geven en geen 500 van de database
        $form->submit([
            'workPackageId' => self::WORK_PACKAGE_ID,
            'taskTitle' => str_repeat('t', 256),
            'taskSlug' => 'te-lange-titel',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('taskTitle')->getErrors());
    }
}
