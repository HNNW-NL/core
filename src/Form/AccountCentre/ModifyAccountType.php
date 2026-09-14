<?php

namespace App\Form\AccountCentre;

use App\Module\AccountCentre\DTO\ModifyAccountDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om je profiel en account gegevens te wijzigen
final class ModifyAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een gebruikersnaam in.')],
            ])
            ->add('firstName', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul je voornaam in.')],
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul je achternaam in.')],
            ])
            ->add('avatarUrl', TextType::class, [
                'required' => false,
            ])
            ->add('location', TextType::class, [
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('language', TextType::class, [
                // de kolom in de database is 5 tekens lang
                'constraints' => [new Length(max: 5, maxMessage: 'Een taalcode is maximaal 5 tekens.')],
            ])
            ->add('theme', ChoiceType::class, [
                'choices' => [
                    'White' => 'light',
                    'Dark' => 'dark',
                ],
            ])
            ->add('profileVisibility', ChoiceType::class, [
                'choices' => [
                    'Public' => 'public',
                    'Private' => 'private',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ModifyAccountDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
