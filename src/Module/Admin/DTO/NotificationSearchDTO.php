<?php

namespace App\Module\Admin\DTO;

use App\Entity\Account\Account;

class NotificationSearchDTO
{
   public string $title  = '';
   public ?Account $user  = null;
}