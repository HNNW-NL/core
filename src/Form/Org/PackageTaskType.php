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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een taak aan een werkpakket toe te voegen
// op de pagina staat er een per werkpakket, dus de controller maakt er meerdere
final class PackageTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('workPackageId', HiddenType::class)
            // de kolommen title en slug zijn 255 tekens in de database, zonder deze regels geeft een te lange tekst een 500
            ->add('taskTitle', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een taaktitel in.'),
                    new Length(max: 255, maxMessage: 'De taaktitel mag maximaal 255 tekens zijn.'),
                ],
            ])
            ->add('taskSlug', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een slug in.'),
                    new Length(max: 255, maxMessage: 'De slug mag maximaal 255 tekens zijn.'),
                ],
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
