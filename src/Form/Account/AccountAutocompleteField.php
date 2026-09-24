<?php

namespace App\Form\Account;

use App\Entity\Account\Account;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class AccountAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Account::class,
            'searchable_fields' => ['username','email'],
            'loading_more_text' => 'Meer resultaten aan het laden',
            'no_results_found_text' => 'Geen resultaten gevonden',
            'no_more_results_text' => 'Geen verdere resultaten gevonden'
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
