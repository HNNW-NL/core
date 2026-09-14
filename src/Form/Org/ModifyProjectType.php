<?php

namespace App\Form\Org;

use App\Module\Org\DTO\ModifyProjectFormDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een bestaand project te wijzigen
// de knoppen modify en delete staan los in de twig, die horen niet in dit formulier
final class ModifyProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('projectId', HiddenType::class)
            ->add('organisationId', HiddenType::class)
            // hiermee kan de backend zien of iemand anders het project ondertussen heeft aangepast
            ->add('lastModified', HiddenType::class, [
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een projecttitel in.')],
            ])
            ->add('summary', TextareaType::class, [
                'constraints' => [new NotBlank(message: 'Vul een samenvatting in.')],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [new NotBlank(message: 'Vul een omschrijving in.')],
            ])
            ->add('visibility', ChoiceType::class, [
                'choices' => [
                    'Public' => 'public',
                    'Private' => 'private',
                    'Unlisted' => 'unlisted',
                ],
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Vul een startdatum in.')],
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('capacity', IntegerType::class, [
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Draft' => 'draft',
                    'Published' => 'published',
                    'Archived' => 'archived',
                ],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ModifyProjectFormDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
