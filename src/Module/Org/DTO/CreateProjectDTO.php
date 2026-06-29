git status<?php 

namespace App\Module\Org\DTO;
use App\Entity\Account\Account;
use App\Entity\Org\Organisation;
use App\Entity\Common\Status;

class CreateProjectDTO
{
    public ?string $name = null;
    public ?string $summary = null;
    public ?string $description = null;
    public ?int $capacity = null;
    public ?string $visibility = null;

    public ?string $slug = null;
    public ?\DateTimeImmutable $startDate = null;
    public ?\DateTimeImmutable $endDate = null;
    public ?bool $remotePossible = null;
    public ?\DateTimeImmutable $publishedAt = null;
    public ?\DateTimeImmutable $deletedAt = null;
    public ?Account $ownerAccount = null;
    public ?Organisation $ownerOrganisation = null;
    public ?Status $status = null;
   

}