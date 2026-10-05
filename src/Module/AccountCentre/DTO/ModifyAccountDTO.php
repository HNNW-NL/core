<?php

namespace App\Module\AccountCentre\DTO;

use Symfony\Component\Validator\Constraints as Assert;

// deze dto vangt het profiel wijzigen formulier op
// de gegevens komen uit drie tabellen (account, profile en account_settings), daarom een dto en geen entity
class ModifyAccountDTO
{
    // hoort bij account
    public ?string $username = null;

    // e-mail is ook waar je mee inlogt, daarom moet hij ingevuld en geldig zijn
    #[Assert\NotBlank(message: 'Vul een e-mailadres in.')]
    #[Assert\Email(message: 'Dit is geen geldig e-mailadres.')]
    public ?string $email = null;

    // hoort bij profile
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $displayName = null;
    public ?string $avatarUrl = null;
    public ?string $location = null;
    public ?string $description = null;

    // hoort bij account_settings
    public ?string $language = 'nl-NL';
    public ?string $theme = 'dark';
    public ?string $profileVisibility = 'private';
}
