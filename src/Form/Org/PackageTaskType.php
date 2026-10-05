<?php

namespace App\Form\Org;

use App\Module\Org\DTO\PackageTaskFormDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een taak aan een werkpakket toe te voegen
// op de pagina staat er een per werkpakket, dus de controller maakt er meerdere
final class PackageTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('workPackageId', HiddenType::class)
            ->add('taskTitle', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een taaktitel in.')],
            ])
            ->add('taskSlug', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een slug in.')],
            ])
            ->add('taskDescription', TextareaType::class, [
                'required' => false,
            ])
            ->add('taskDueDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('taskPriority', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PackageTaskFormDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
