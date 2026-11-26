<?php

namespace App\Form;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Account\Skill;
use App\Enum\SkillProficiencyLevel;

class ProfileSkillType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('skill', EntityType::class, [
                'class' => Skill::class,
                'choice_label' => 'name',
                'label' => 'Vaardigheid',
                'placeholder' => 'Zoek een vaardigheid...',
                'autocomplete' => true,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('s')
                        ->where('s.active = :active')
                        ->setParameter('active', true)
                        ->orderBy('s.name', 'ASC');
                },
            ])
            ->add('proficiencyLevel', EnumType::class, [
                'class' => SkillProficiencyLevel::class,
                'choice_label' => 'value',
                'label' => 'Niveau',
                'placeholder' => 'Kies een niveau...',
                'autocomplete' => true,
            ])
        ;
    }
}
