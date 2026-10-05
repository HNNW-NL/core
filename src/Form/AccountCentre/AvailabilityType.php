<?php

namespace App\Form\AccountCentre;

use App\Module\AccountCentre\DTO\AvailabilityDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// formulier voor het weekrooster in het account centre
// daysConfig is een lijst van zeven dagen, daar is CollectionType voor
final class AvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('availabilityType', HiddenType::class)
            ->add('hoursPerWeek', IntegerType::class, [
                'required' => false,
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('daysConfig', CollectionType::class, [
                'entry_type' => DayAvailabilityType::class,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AvailabilityDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
