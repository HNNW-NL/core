<?php

namespace App\Module\Admin\DTO;

use App\Entity\Account\Account;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class AuditLogSearchDTO
{
   public ?DateTimeImmutable $startPeriod = null;   
   #[Assert\Expression(
       "this.startPeriod == null or this.endPeriod == null or this.startPeriod <= this.endPeriod",
        message:"De einddatum moet na of hetzelfde als de startdatum zijn"
   )]
   public ?DateTimeImmutable $endPeriod = null;
   public ?Account $actor  = null;
}