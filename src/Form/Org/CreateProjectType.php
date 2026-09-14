<?php

namespace App\Form\Org;

use App\Entity\Common\Status;
use App\Module\Org\DTO\CreateProjectDTO;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

// formulier om een nieuw project aan te maken
// het gebruikt de CreateProjectDTO die al in het project stond, daar heet de titel name
final class CreateProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [new NotBlank(message: 'Vul een projectnaam in.')],
            ])
            ->add('summary', TextareaType::class, [
                'constraints' => [new NotBlank(message: 'Vul een samenvatting in.')],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [new NotBlank(message: 'Vul een omschrijving in.')],
            ])
            ->add('capacity', IntegerType::class, [
                'required' => false,
            ])
            ->add('visibility', ChoiceType::class, [
                'choices' => [
                    'Public' => 'public',
                    'Private' => 'private',
                ],
                'placeholder' => 'Choose visibility',
            ])
            // de statussen stonden eerst in een aparte twig component, nu haalt symfony ze zelf op
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
            'data_class' => CreateProjectDTO::class,
            'csrf_message' => 'Het beveiligingstoken is ongeldig. Probeer het formulier opnieuw te verzenden.',
        ]);
    }
}
