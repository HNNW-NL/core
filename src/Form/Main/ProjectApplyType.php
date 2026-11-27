<?php

namespace App\Form\Main;

use App\Module\Main\DTO\ProjectApplyDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectApplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('motivation', TextareaType::class, [
            'label' => 'Motivatie',
            'required' => true,
            'attr' => [
                'rows' => 8,
                'maxlength' => 2000,
                'placeholder' => 'Waarom wil je meedoen aan dit project?',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectApplyDto::class,
            'csrf_token_id' => 'project_apply',
        ]);
    }
}
