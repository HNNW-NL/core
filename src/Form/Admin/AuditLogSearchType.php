<?php

namespace App\Form\Admin;

use App\Form\Account\AccountAutocompleteField;
use App\Module\Admin\DTO\AuditLogSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AuditLogSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder            
             ->add('actor', AccountAutocompleteField::class,
                ['multiple' => false,'attr' =>['autocomplete' => 'off']]
            )->add('startPeriod', DateTimeType::class,
                ['required' => false,'attr' =>['autocomplete' => 'off']])
            ->add('endPeriod', DateTimeType::class,
                ['required' => false,'attr' =>['autocomplete' => 'off']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AuditLogSearchDTO::class,
        ]);
    }
}
