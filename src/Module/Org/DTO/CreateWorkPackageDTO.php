<?php

namespace App\Module\Org\DTO;

class CreateWorkPackageDTO
{
    public function __construct(
        public readonly string $projectId,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?\DateTimeImmutable $dueDate,
    ) {
    }
}