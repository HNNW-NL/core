<?php

namespace App\Module\Main\DTO;

use Symfony\Component\Validator\Constraints as Assert;

// dit vult het aanmeld formulier in, de regels staan hier zodat het formulier ze zelf checkt
final class ProjectApplyDto
{
    #[Assert\NotBlank(message: 'Vul een motivatie in.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Je motivatie moet minstens {{ limit }} tekens zijn.',
        maxMessage: 'Je motivatie mag maximaal {{ limit }} tekens zijn.',
    )]
    public ?string $motivation = null;
}
