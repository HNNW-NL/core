<?php

namespace App\Module\Admin\DTO;

use DateTimeImmutable;

class SystemLogSearchDisplayDTO
{
   public ?DateTimeImmutable $createdAt = null;   
   public ?int $code = null;   
   public ?string $level = '';
   public ?string $message = '';
   public ?string $route = '';
   public ?string $method = '';
   public ?string $requestIp = '';
}