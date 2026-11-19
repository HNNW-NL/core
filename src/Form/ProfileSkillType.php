<?php

namespace App\Form;

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
                'autocomplete' => true,
            ])
            ->add('proficiencyLevel', EnumType::class, [
                'class' => SkillProficiencyLevel::class,
                'choice_label' => 'value',
                'label' => 'Niveau',
                'autocomplete' => true,
            ])
        ;
    }
}
