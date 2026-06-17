<?php

namespace App\Twig\Components\Org;

use App\Entity\Account\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class ProfileAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Profile::class,
            'choice_label' => fn(Profile $profile) =>
                $profile->getDisplayName()
                ?? $profile->getFirstName().' '.$profile->getLastName(),
            'searchable_fields' => ['display_name'],
            'placeholder' => 'Search profiles...',
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
