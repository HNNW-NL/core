<?php

namespace App\Tests\Form\Org;

use App\Entity\Common\Status;
use App\Form\Org\ModifyProjectType;
use App\Module\Org\DTO\ModifyProjectFormDTO;
use App\Repository\Common\StatusRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

// dit formulier heeft een EntityType voor de status en die heeft doctrine nodig
// daarom gebruiken we de echte form factory uit de kernel en niet de kale TypeTestCase
class ModifyProjectTypeTest extends KernelTestCase
{
    // verzonnen uuid's, het formulier geeft ze alleen door en zoekt er niets mee op in de database
    private const PROJECT_ID = '01a10c37-444c-7151-8802-976a90921ce7';
    private const ORGANISATION_ID = '01a10c37-4373-73b1-9aea-9d7f7e03f295';

    private function makeForm(ModifyProjectFormDTO $dto): FormInterface
    {
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);

        // in deze test is er geen pagina die een csrf token kan maken, dus die controle zetten we uit
        return $formFactory->create(ModifyProjectType::class, $dto, [
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

        $dto = new ModifyProjectFormDTO();
        $dto->projectId = self::PROJECT_ID;
        $dto->title = 'Test Project';
        $form = $this->makeForm($dto);

        $this->assertSame($dto, $form->getData());
        $this->assertTrue($form->has('projectId'));
        $this->assertTrue($form->has('organisationId'));
        $this->assertTrue($form->has('lastModified'));
        $this->assertTrue($form->has('title'));
        $this->assertTrue($form->has('summary'));
        $this->assertTrue($form->has('description'));
        $this->assertTrue($form->has('visibility'));
        $this->assertTrue($form->has('startDate'));
        $this->assertTrue($form->has('endDate'));
        $this->assertTrue($form->has('capacity'));
        $this->assertTrue($form->has('status'));
        // de waarden uit de dto komen in de velden terecht
        $this->assertSame(self::PROJECT_ID, $form->get('projectId')->getData());
        $this->assertSame('Test Project', $form->get('title')->getData());
    }

    public function testWhenSubmittedShouldFillDto(): void
    {
        self::bootKernel();

        $status = $this->findProjectStatus();
        $dto = new ModifyProjectFormDTO();
        $form = $this->makeForm($dto);

        $form->submit([
            'projectId' => self::PROJECT_ID,
            'organisationId' => self::ORGANISATION_ID,
            'lastModified' => '2026-10-05 12:00:00',
            'title' => 'Aangepaste titel',
            'summary' => 'Aangepaste samenvatting',
            'description' => 'Aangepaste omschrijving',
            'visibility' => 'private',
            'startDate' => '2026-11-01',
            'endDate' => '2026-12-31',
            'capacity' => '12',
            'status' => (string) $status->getId(),
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame(self::PROJECT_ID, $dto->projectId);
        $this->assertSame(self::ORGANISATION_ID, $dto->organisationId);
        $this->assertSame('2026-10-05 12:00:00', $dto->lastModified);
        $this->assertSame('Aangepaste titel', $dto->title);
        $this->assertSame('Aangepaste samenvatting', $dto->summary);
        $this->assertSame('Aangepaste omschrijving', $dto->description);
        $this->assertSame('private', $dto->visibility);
        $this->assertSame('2026-11-01', $dto->startDate->format('Y-m-d'));
        $this->assertSame('2026-12-31', $dto->endDate->format('Y-m-d'));
        $this->assertSame(12, $dto->capacity);
        $this->assertInstanceOf(Status::class, $dto->status);
        $this->assertSame((string) $status->getId(), (string) $dto->status->getId());
    }

    public function testWhenTitleIsEmptyShouldBeInvalid(): void
    {
        self::bootKernel();

        $form = $this->makeForm(new ModifyProjectFormDTO());

        $form->submit([
            'projectId' => self::PROJECT_ID,
            'organisationId' => self::ORGANISATION_ID,
            'title' => '',
            'summary' => 'Aangepaste samenvatting',
            'description' => 'Aangepaste omschrijving',
            'visibility' => 'public',
            'startDate' => '2026-11-01',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('title')->getErrors());
    }

    public function testWhenTitleIsTooLongShouldBeInvalid(): void
    {
        self::bootKernel();

        $form = $this->makeForm(new ModifyProjectFormDTO());

        // de pagina belooft maximaal 120 tekens voor de titel
        $form->submit([
            'projectId' => self::PROJECT_ID,
            'organisationId' => self::ORGANISATION_ID,
            'title' => str_repeat('t', 121),
            'summary' => 'Aangepaste samenvatting',
            'description' => 'Aangepaste omschrijving',
            'visibility' => 'public',
            'startDate' => '2026-11-01',
        ]);

        $this->assertFalse($form->isValid());
        $this->assertCount(1, $form->get('title')->getErrors());
    }

    public function testStatusShouldBeEntityChoiceWithProjectStatuses(): void
    {
        self::bootKernel();

        $form = $this->makeForm(new ModifyProjectFormDTO());

        $this->assertInstanceOf(EntityType::class, $form->get('status')->getConfig()->getType()->getInnerType());

        $choices = $form->createView()['status']->vars['choices'];

        $this->assertNotEmpty($choices);
        foreach ($choices as $choice) {
            $this->assertInstanceOf(Status::class, $choice->data);
            $this->assertSame('project', $choice->data->getScope());
        }
    }
}
