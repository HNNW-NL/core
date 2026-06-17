<?php

namespace App\Twig\Components\Org;

use App\Twig\Components\Org\ProfileAutocompleteField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class InviteParticipantsForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('profile', ProfileAutocompleteField::class);
    }
}
