<?php

namespace App\Form\Account;

use App\Entity\Common\Status;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

#[AsEntityAutocompleteField]
class AccountStatusAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Status::class,
            'searchable_fields' => ['name'],
            'query_builder' => function (EntityRepository $er) {
                    $qb = $er->createQueryBuilder('s');                    
                    return $qb->andWhere('lower(s.scope) = :scope')->setParameter(':scope','account');
            },
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
