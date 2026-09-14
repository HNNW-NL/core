<?php

namespace App\Form\AccountCentre;

use App\Module\AccountCentre\DTO\NotificationSettingsDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// formulier voor de notificatie voorkeuren in het account centre
// het hangt aan een dto en niet aan de entity, zo kunnen beide vinkjes apart bestaan
final class NotificationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('emailNotificationsEnabled', CheckboxType::class, [
                'label' => 'Nieuwe projecten',
                'required' => false,
            ])
            ->add('newsletterEnabled', CheckboxType::class, [
                'label' => 'Maandelijkse nieuwsbrief',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NotificationSettingsDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
