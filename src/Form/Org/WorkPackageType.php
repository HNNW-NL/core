<?php

namespace App\Form\Org;

use App\Module\Org\DTO\WorkPackageFormDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een nieuw werkpakket aan te maken
final class WorkPackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een titel in.')],
            ])
            ->add('slug', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een slug in.')],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('dueDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WorkPackageFormDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
