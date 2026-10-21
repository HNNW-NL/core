<?php

namespace App\Form\Admin;

use App\Form\Account\AccountAutocompleteField;
use App\Form\Account\AccountStatusAutocompleteField;
use App\Module\Admin\DTO\AccountSearchDTO;
use App\Module\Admin\DTO\SystemLogSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccountSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder            
             ->add('account', AccountAutocompleteField::class,
                ['label' => 'Gebruiker','multiple' => false,'attr' =>['autocomplete' => 'off']]
            )->add('status', AccountStatusAutocompleteField::class,
                ['label' => 'Status','required' => false,'attr' =>['autocomplete' => 'off']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccountSearchDTO::class,
        ]);
    }
}
