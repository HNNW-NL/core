<?php

namespace App\Module\Admin\DTO;

use App\Entity\Common\Status;
use DateTimeImmutable;
use App\Twig\Components\Shared\Search;
use App\Twig\Components\Shared\Search\Path;
use App\Twig\Components\Shared\Search\PathParameter;
use Symfony\Component\ObjectMapper\Attribute\Map;

#[PathParameter('id')]
class AccountSearchDisplayDTO
{   
   #[Search\Label("Geregistreert op")]
   #[Path('admin.accounts.modify')]
   public ?DateTimeImmutable $createdAt = null;  

   #[Search\Label("Gebruikersnaam")]
   #[Path('admin.accounts.modify')]
   public ?string $username = null;   

   #[Search\Label("Email")] 
   #[Path('admin.accounts.modify')]
   public ?string $email = ''; 

   #[Map(source:'status',transform:[AccountSearchDisplayDTO::class,'toStatus'])]
   #[Search\Label("Status")]
   #[Path('admin.accounts.modify')]
   public ?string $status = '';  

   #[Map(source:'emailVerifiedAt', transform:[AccountSearchDisplayDTO::class, 'toVerificationStatus'])]
   #[Search\Label('Verificatiestatus')]
   #[Path('admin.accounts.modify')]
   public string $verificationStatus = 'Geverificeerd';  

   static function toVerificationStatus(?DateTimeImmutable $emailVerifiedAt)
   {
      return ($emailVerifiedAt != null) ? 'Geverificeerd': 'Niet geverificeerd';
   } 

   static function toStatus(?Status $status)
   {
      return ($status != null) ? $status->getName() : 'Onbekend';
   } 
}