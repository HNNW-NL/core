<?php

namespace App\Module\Admin\DTO;

use DateTimeImmutable;
use App\Twig\Components\Shared\Search;

class SystemLogSearchDisplayDTO
{   
   #[Search\Label("Gemaakt op")]
   public ?DateTimeImmutable $createdAt = null;  

   #[Search\Label("Code")]
   public ?int $code = null;   

   #[Search\Label("Level")] 
   public ?string $level = ''; 

   #[Search\Label("Bericht")]
   public ?string $message = '';  

   #[Search\Label("Route")]
   public ?string $route = '';  
    
   #[Search\Label("Methode")]
   public ?string $method = '';

   #[Search\Label("Ip")]
   public ?string $requestIp = '';
}