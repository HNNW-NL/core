<?php

namespace App\Form\AccountCentre;

use App\Module\AccountCentre\DTO\ExperienceDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een werkervaring toe te voegen in het account centre
// de keuzelijsten stonden eerst in de twig, die staan nu hier
final class ExperienceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('jobTitle', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een functietitel in.')],
            ])
            ->add('employmentType', ChoiceType::class, [
                'choices' => [
                    'Vast dienstverband (onbepaalde tijd)' => 'vast',
                    'Tijdelijk dienstverband (bepaalde tijd)' => 'tijdelijk',
                    'Oproepcontract (nuluren / min-max)' => 'oproep',
                    'Uitzendbasis / Detachering' => 'uitzend_detachering',
                    'Zelfstandige zonder personeel (zzp / freelance)' => 'zzp_freelance',
                    'Stage / Afstuderen' => 'stage_afstuderen',
                    'Payroll' => 'payroll',
                ],
                'placeholder' => 'Kies een optie...',
            ])
            ->add('organisationName', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een bedrijf of organisatie in.')],
            ])
            ->add('isCurrent', CheckboxType::class, [
                'required' => false,
            ])
            // single_text geeft een datumveld in plaats van drie losse keuzelijsten
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
            ->add('location', TextType::class, [
                'required' => false,
            ])
            ->add('locationType', ChoiceType::class, [
                'choices' => [
                    'On-site' => 'onsite',
                    'Hybride' => 'hybrid',
                    'Remote' => 'remote',
                ],
                'required' => false,
            ])
            ->add('profileHeadline', TextType::class, [
                'required' => false,
            ])
            ->add('vacancySource', ChoiceType::class, [
                'choices' => [
                    'Hier' => 'hier',
                    'LinkedIn' => 'linkedin',
                    'Website organisatie' => 'website_organisatie',
                    'Indeed' => 'indeed',
                    'Andere vacature websites' => 'andere_vacaturesites',
                    'Aanbeveling' => 'aanbeveling',
                    'Via een recruiter' => 'recruiter',
                    'Anders' => 'anders',
                ],
                'required' => false,
            ])
            ->add('skills', TextType::class, [
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ExperienceDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
