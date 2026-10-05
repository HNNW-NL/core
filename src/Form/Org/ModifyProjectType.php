<?php

namespace App\Form\Org;

use App\Entity\Common\Status;
use App\Module\Org\DTO\ModifyProjectFormDTO;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

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
            // de pagina belooft max 120, 280 en 500 tekens en de oude ModifyProjectService controleerde dat ook, dus de server doet dat nu weer
            ->add('title', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een projecttitel in.'),
                    new Length(max: 120, maxMessage: 'De projecttitel mag maximaal 120 tekens zijn.'),
                ],
            ])
            ->add('summary', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een samenvatting in.'),
                    new Length(max: 280, maxMessage: 'De samenvatting mag maximaal 280 tekens zijn.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'Vul een omschrijving in.'),
                    new Length(max: 500, maxMessage: 'De omschrijving mag maximaal 500 tekens zijn.'),
                ],
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
            // de pagina belooft 1 tot 500, leeg mag wel want de controller maakt daar 0 van
            ->add('capacity', IntegerType::class, [
                'required' => false,
                'constraints' => [new Range(min: 1, max: 500, notInRangeMessage: 'Vul een getal van 1 tot 500 in.')],
            ])
            // de statussen draft, published en archived staan niet in de database, daarom haalt symfony ze nu zelf op net als bij aanmaken
            ->add('status', EntityType::class, [
                'class' => Status::class,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $repo) => $repo->createQueryBuilder('s')
                    ->where('s.scope = :scope')
                    ->setParameter('scope', 'project')
                    ->orderBy('s.name', 'ASC'),
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
