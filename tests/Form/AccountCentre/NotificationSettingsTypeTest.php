<?php

namespace App\Tests\Form\AccountCentre;

use App\Form\AccountCentre\NotificationSettingsType;
use App\Module\AccountCentre\DTO\NotificationSettingsDTO;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class NotificationSettingsTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        // symfony's TypeTestCase maakt zelf een mock-dispatcher en phpunit 13 geeft een melding bij een mock zonder verwachtingen; een stub (lege vulling) is hier genoeg
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        // de dto heeft geen constraints, dus alleen de csrf-extensie is nodig: het formulier zet de optie csrf_message en die bestaat anders niet
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);

        return [
            new CsrfExtension($csrfTokenManager),
        ];
    }

    public function testWhenCheckboxIsCheckedShouldFillDtoWithTrue(): void
    {
        $dto = new NotificationSettingsDTO();
        $form = $this->factory->create(NotificationSettingsType::class, $dto, ['csrf_protection' => false]);

        $form->submit(['emailNotificationsEnabled' => '1']);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertTrue($dto->emailNotificationsEnabled);
    }

    public function testWhenCheckboxIsNotSentShouldFillDtoWithFalse(): void
    {
        $dto = new NotificationSettingsDTO();
        $form = $this->factory->create(NotificationSettingsType::class, $dto, ['csrf_protection' => false]);

        // een vinkje dat uit staat stuurt een browser helemaal niet mee, de dto moet dan van true naar false gaan
        $form->submit([]);

        $this->assertTrue($form->isValid());
        $this->assertFalse($dto->emailNotificationsEnabled);
    }

    public function testShouldNotHaveNewsletterField(): void
    {
        $form = $this->factory->create(NotificationSettingsType::class, new NotificationSettingsDTO(), ['csrf_protection' => false]);

        // het nieuwsbrief vinkje is weggehaald, het schreef naar dezelfde kolom als het andere vinkje
        $this->assertFalse($form->has('newsletterEnabled'));
        $this->assertSame(['emailNotificationsEnabled'], array_keys($form->all()));
    }
}
