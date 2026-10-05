<?php

namespace App\Tests\Form\Org;

use App\Form\Org\WorkPackageType;
use App\Module\Org\DTO\WorkPackageFormDTO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class WorkPackageTypeTest extends TypeTestCase
{
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
        $dto = new WorkPackageFormDTO();

        $form = $this->factory->create(WorkPackageType::class, $dto);

        $this->assertSame($dto, $form->getData());
        $this->assertTrue($form->has('title'));
        $this->assertTrue($form->has('slug'));
        $this->assertTrue($form->has('description'));
        $this->assertTrue($form->has('dueDate'));
    }

    public function testWhenSubmittedShouldFillDto(): void
    {
        $dto = new WorkPackageFormDTO();
        $form = $this->factory->create(WorkPackageType::class, $dto);

        $form->submit([
            'title' => 'Nieuw werkpakket',
            'slug' => 'nieuw-werkpakket',
            'description' => 'Omschrijving van het werkpakket',
            'dueDate' => '2026-12-31',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Nieuw werkpakket', $dto->title);
        $this->assertSame('nieuw-werkpakket', $dto->slug);
        $this->assertSame('Omschrijving van het werkpakket', $dto->description);
        $this->assertSame('2026-12-31', $dto->dueDate->format('Y-m-d'));
    }

    public function testWhenTitleIsEmptyShouldBeInvalid(): void
    {
        $form = $this->factory->create(WorkPackageType::class, new WorkPackageFormDTO());

        $form->submit([
            'title' => '',
            'slug' => 'zonder-titel',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('title')->getErrors());
    }
}
