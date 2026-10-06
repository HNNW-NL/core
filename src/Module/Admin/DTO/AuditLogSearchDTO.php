<?php

namespace App\Module\Admin\DTO;

use App\Entity\Account\Account;
use DateTimeImmutable;

class AuditLogSearchDTO
{
   public ?DateTimeImmutable $startPeriod  = null;
   public ?DateTimeImmutable $endPeriod  = null;
   public ?Account $actor  = null;
}