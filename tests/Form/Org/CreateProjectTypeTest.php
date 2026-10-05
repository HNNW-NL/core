<?php

namespace App\Tests\Form\Org;

use App\Entity\Common\Status;
use App\Form\Org\CreateProjectType;
use App\Module\Org\DTO\CreateProjectDTO;
use App\Repository\Common\StatusRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

// dit formulier heeft een EntityType voor de status en die heeft doctrine nodig
// daarom gebruiken we de echte form factory uit de kernel en niet de kale TypeTestCase
class CreateProjectTypeTest extends KernelTestCase
{
    private function makeForm(CreateProjectDTO $dto): FormInterface
    {
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        // in deze test is er geen pagina die een csrf token kan maken, dus die controle zetten we uit
        return $formFactory->create(CreateProjectType::class, $dto, [
            'csrf_protection' => false,
        ]);
    }

    private function findProjectStatus(): Status
    {
        return static::getContainer()->get(StatusRepository::class)->findOneByScopeAndName('project', 'active');
    }

    public function testShouldBuildFormWithDto(): void
    {
        self::bootKernel();

        $dto = new CreateProjectDTO();
        $form = $this->makeForm($dto);

        $this->assertSame($dto, $form->getData());
        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('summary'));
        $this->assertTrue($form->has('description'));
        $this->assertTrue($form->has('capacity'));
        $this->assertTrue($form->has('visibility'));
        $this->assertTrue($form->has('status'));
    }

    public function testWhenSubmittedShouldFillDto(): void
    {
        self::bootKernel();

        $status = $this->findProjectStatus();
        $dto = new CreateProjectDTO();
        $form = $this->makeForm($dto);

        $form->submit([
            'name' => 'Nieuw project',
            'summary' => 'Een korte samenvatting',
            'description' => 'Een langere omschrijving',
            'capacity' => '5',
            'visibility' => 'public',
            'status' => (string) $status->getId(),
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Nieuw project', $dto->name);
        $this->assertSame('Een korte samenvatting', $dto->summary);
        $this->assertSame('Een langere omschrijving', $dto->description);
        $this->assertSame(5, $dto->capacity);
        $this->assertSame('public', $dto->visibility);
        $this->assertInstanceOf(Status::class, $dto->status);
        $this->assertSame((string) $status->getId(), (string) $dto->status->getId());
    }

    public function testWhenVisibilityIsEmptyShouldBeInvalid(): void
    {
        self::bootKernel();

        $form = $this->makeForm(new CreateProjectDTO());

        $form->submit([
            'name' => 'Nieuw project',
            'summary' => 'Een korte samenvatting',
            'description' => 'Een langere omschrijving',
            'visibility' => '',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('visibility')->getErrors());
    }

    public function testWhenNameIsTooLongShouldBeInvalid(): void
    {
        self::bootKernel();

        $form = $this->makeForm(new CreateProjectDTO());

        // de kolom title is 255 tekens, dus 256 tekens moet een nette fout geven en geen 500 van de database
        $form->submit([
            'name' => str_repeat('t', 256),
            'summary' => 'Een korte samenvatting',
            'description' => 'Een langere omschrijving',
            'visibility' => 'public',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('name')->getErrors());
    }

    public function testStatusShouldBeEntityChoiceWithProjectStatuses(): void
    {
        self::bootKernel();

        $form = $this->makeForm(new CreateProjectDTO());

        $this->assertInstanceOf(EntityType::class, $form->get('status')->getConfig()->getType()->getInnerType());

        $choices = $form->createView()['status']->vars['choices'];

        $this->assertNotEmpty($choices);
        foreach ($choices as $choice) {
            $this->assertInstanceOf(Status::class, $choice->data);
            $this->assertSame('project', $choice->data->getScope());
        }
    }
}
