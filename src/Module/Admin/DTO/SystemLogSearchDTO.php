<?php

namespace App\Module\Admin\DTO;

use App\Module\Admin\Service\Paginate\Attributes as Paginate;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class SystemLogSearchDTO
{
   #[Paginate\WhereTarget('createdAt','>')]
   public ?DateTimeImmutable $startPeriod = null;   
   #[Assert\Expression(
       "this.startPeriod == null or this.endPeriod == null or this.startPeriod <= this.endPeriod",
        message:"De einddatum moet na of hetzelfde als de startdatum zijn"
   )]
   #[Paginate\WhereTarget('createdAt','<')]
   public ?DateTimeImmutable $endPeriod = null;
   public ?string $level    = null;
}