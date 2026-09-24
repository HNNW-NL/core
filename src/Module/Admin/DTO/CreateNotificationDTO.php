<?php

namespace App\Module\Admin\DTO;

use App\Entity\Account\Account;
use Symfony\Component\Validator\Constraints as Assert;

class CreateNotificationDTO
{
   #[Assert\NotNull]
   public ?Account $account  = null;

   public ?Account $senderAccount = null;

   #[Assert\NotBlank]
   #[Assert\Length(
        max: 255,
        maxMessage: 'De title mag maximaal {{ limit }} tekenes lang zijn',
    )]
   public string $title  = '';

    #[Assert\NotBlank]
    #[Assert\Length(
        max: 50,
        maxMessage: 'Het type mag maximaal {{ limit }} tekenes lang zijn',
    )]
   public string $type = '';

   #[Assert\NotBlank]
   public string $message = '';

   
}