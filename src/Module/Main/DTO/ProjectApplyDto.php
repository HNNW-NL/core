<?php

namespace App\Module\Main\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ProjectApplyDto
{
    #[Assert\NotBlank(message: 'Vul een motivatie in.')]
    #[Assert\Length(
        min: 20,
        max: 2000,
        minMessage: 'Je motivatie moet minstens {{ limit }} tekens zijn.',
        maxMessage: 'Je motivatie mag maximaal {{ limit }} tekens zijn.',
    )]
    public ?string $motivation = null;
}
