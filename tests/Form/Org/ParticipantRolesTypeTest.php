<?php

namespace App\Tests\Form\Org;

use App\Form\Org\ParticipantRolesType;
use App\Module\Org\DTO\ParticipantRolesDTO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class ParticipantRolesTypeTest extends TypeTestCase
{
    // verzonnen uuid's, het formulier geeft ze alleen door en zoekt er niets mee op in de database
    private const PARTICIPANT_ID = '01a10c37-4451-7000-8000-000000000001';
    private const DEVELOPER_ROLE_ID = '01a10c37-4445-7000-8000-000000000001';
    private const TESTER_ROLE_ID = '01a10c37-4445-7000-8000-000000000002';

    // de basisklasse maakt zelf een mock dispatcher zonder verwachtingen en daar geeft phpunit een notice op, een stub is dan netter
    protected function setUp(): void
    {
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    // een rol die niet in de keuzelijst staat moet een fout geven, daar is de validator extensie voor nodig
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
        ];
    }

    // de controller geeft de rollen van het project mee als keuzelijst, naam is het label en id is de waarde
    private function roleChoices(): array
    {
        return [
            'Developer' => self::DEVELOPER_ROLE_ID,
            'Tester' => self::TESTER_ROLE_ID,
        ];
    }

    public function testShouldBuildFormWithDto(): void
    {
        $dto = new ParticipantRolesDTO();
        $dto->role[self::PARTICIPANT_ID] = self::DEVELOPER_ROLE_ID;

        $form = $this->factory->create(ParticipantRolesType::class, $dto, [
            'role_choices' => $this->roleChoices(),
        ]);

        $this->assertSame($dto, $form->getData());
        // per deelnemer in de dto komt er een keuzeveld in de collectie
        $this->assertTrue($form->get('role')->has(self::PARTICIPANT_ID));
        $this->assertSame(self::DEVELOPER_ROLE_ID, $form->get('role')->get(self::PARTICIPANT_ID)->getData());
    }

    public function testWhenSubmittedShouldFillDto(): void
    {
        $dto = new ParticipantRolesDTO();
        $dto->role[self::PARTICIPANT_ID] = self::DEVELOPER_ROLE_ID;

        $form = $this->factory->create(ParticipantRolesType::class, $dto, [
            'role_choices' => $this->roleChoices(),
        ]);

        $form->submit([
            'role' => [
                self::PARTICIPANT_ID => self::TESTER_ROLE_ID,
            ],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame(self::TESTER_ROLE_ID, $dto->role[self::PARTICIPANT_ID]);
    }

    public function testWhenRoleIsNotInChoicesShouldBeInvalid(): void
    {
        $dto = new ParticipantRolesDTO();
        $dto->role[self::PARTICIPANT_ID] = self::DEVELOPER_ROLE_ID;

        $form = $this->factory->create(ParticipantRolesType::class, $dto, [
            'role_choices' => $this->roleChoices(),
        ]);

        $form->submit([
            'role' => [
                self::PARTICIPANT_ID => '01a10c37-4445-7000-8000-000000000099',
            ],
        ]);

        $this->assertFalse($form->isValid());
        $this->assertFalse($form->get('role')->get(self::PARTICIPANT_ID)->isSynchronized());
    }
}
