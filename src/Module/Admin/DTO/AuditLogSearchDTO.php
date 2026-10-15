<?php

namespace App\Module\Admin\DTO;

use App\Entity\Account\Account;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;
use App\Module\Admin\Service\Paginate\Attributes as Paginate;

class AuditLogSearchDTO
{
   #[Paginate\WhereTarget('createdAt','>')]
   public ?DateTimeImmutable $startPeriod = null;   

   #[Assert\Expression(
       "this.startPeriod == null or this.endPeriod == null or this.startPeriod <= this.endPeriod",
        message:"De einddatum moet na of hetzelfde als de startdatum zijn"
   )]
   #[Paginate\WhereTarget('createdAt','<')]
   public ?DateTimeImmutable $endPeriod = null;

   #[Paginate\Join('Account','actorAccount')]
   #[Paginate\WhereTarget('id','=')]
   public ?Account $actor  = null;
   
}