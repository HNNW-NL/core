<?php

namespace App\Form\Admin;

use App\Form\Account\AccountAutocompleteField;
use App\Module\Admin\DTO\CreateNotificationDTO;
use App\Module\Admin\DTO\NotificationSearchDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class CreateNotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', AccountAutocompleteField::class,
            ['multiple' => false,
            'attr' => ['required' => true]])
            ->add('title', TextType::class)
            ->add('type', TextType::class)
            ->add('message', TextareaType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateNotificationDTO::class,
        ]);
    }
}
