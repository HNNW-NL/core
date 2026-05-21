<?php 

namespace App\Module\Org\DTO;

class CreateProjectDTO
{
    public ?string $name = null;
    public ?string $summary = null;
    public ?string $description = null;
    public ?int $capacity = null;
    public ?string $visibility = null;
}