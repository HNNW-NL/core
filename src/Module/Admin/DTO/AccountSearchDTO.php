<?php

namespace App\Module\Admin\DTO;

use App\Entity\Account\Account;
use App\Entity\Common\Status;
use App\Module\Admin\Service\Paginate\Attributes as Paginate;

class AccountSearchDTO
{
   #[Paginate\Join('Status','status')]
   public ?Status $status  = null;

   public ?Account $account = null;
}