<?php

namespace App\Module\Admin\DTO;

use DateTimeImmutable;

class AuditLogSearchDisplayDTO
{
   public ?DateTimeImmutable $createdAt = null;   
   public ?string $actorUsername = '';
   public ?string $actorEmail = '';
   public ?string $action = '';
   public ?string $entityType = '';
   public ?string $entityId = '';
   public ?string $requestIp = '';
}