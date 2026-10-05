<?php

namespace App\Form\Org;

use App\Module\Org\DTO\ParticipantRolesDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// formulier om per deelnemer een rol te kiezen
// de controller geeft de lijst met rollen mee en welke deelnemers er zijn
final class ParticipantRolesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('role', CollectionType::class, [
            'entry_type' => ChoiceType::class,
            'entry_options' => [
                'choices' => $options['role_choices'],
                'label' => false,
            ],
            'label' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParticipantRolesDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
            // de rollen komen uit de database, dus die geeft de controller mee
            'role_choices' => [],
        ]);

        $resolver->setAllowedTypes('role_choices', 'array');
    }
}
