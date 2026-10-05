<?php

namespace App\Form\Org;

use App\Module\Org\DTO\WorkPackageFormDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een nieuw werkpakket aan te maken
final class WorkPackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // de kolommen title en slug zijn 255 tekens in de database, zonder deze regels geeft een te lange tekst een 500
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een titel in.'),
                    new Length(max: 255, maxMessage: 'De titel mag maximaal 255 tekens zijn.'),
                ],
            ])
            ->add('slug', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een slug in.'),
                    new Length(max: 255, maxMessage: 'De slug mag maximaal 255 tekens zijn.'),
                ],
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
