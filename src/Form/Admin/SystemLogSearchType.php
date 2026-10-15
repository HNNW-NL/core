<?php

namespace App\Form\Admin;

use App\Module\Admin\DTO\SystemLogSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SystemLogSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder            
             ->add('level', TextType::class,
             ['required' => false,'attr' =>['autocomplete' => 'off']]
            )->add('startPeriod', DateTimeType::class,
                ['required' => false,'attr' =>['autocomplete' => 'off']])
            ->add('endPeriod', DateTimeType::class,
                ['required' => false,'attr' =>['autocomplete' => 'off']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SystemLogSearchDTO::class,
        ]);
    }
}
