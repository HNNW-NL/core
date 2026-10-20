<?php

namespace App\Module\Admin\DTO;

use DateTimeImmutable;
use App\Twig\Components\Shared\Search;

class AuditLogSearchDisplayDTO
{   
   
   #[Search\Label("Gemaakt op")]
   public ?DateTimeImmutable $createdAt = null; 

   #[Search\Label("Gebruikersnaam")]
   public ?string $actorUsername = '';

   #[Search\Label("Gebruikersemail")]
   public ?string $actorEmail = '';

   #[Search\Label("Actie")]
   public ?string $action = '';

   #[Search\Label("Entiteittype")]
   public ?string $entityType = '';

   #[Search\Label("Entiteitid")]
   public ?string $entityId = '';

   #[Search\Label("Ip")]
   public ?string $requestIp = '';
}