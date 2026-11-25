<?php

namespace App\Form\AccountCentre;

use App\Module\AccountCentre\DTO\DayAvailabilityDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// een rij uit het weekrooster: aan of uit, met een begin en een eindtijd
final class DayAvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', CheckboxType::class, [
                'required' => false,
            ])
            ->add('start', TimeType::class, [
                'widget' => 'single_text',
                'input' => 'string',
                'required' => false,
            ])
            ->add('end', TimeType::class, [
                'widget' => 'single_text',
                'input' => 'string',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DayAvailabilityDTO::class,
        ]);
    }
}
