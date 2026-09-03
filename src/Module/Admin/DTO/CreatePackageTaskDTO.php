<?php

namespace App\Module\Admin\DTO;

class CreatePackageTaskDTO
{
    public function __construct(
        public readonly string $workPackageId,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?\DateTimeImmutable $dueDate,
        public readonly string $priority,
    ) {
    }
}
