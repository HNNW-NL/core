<?php

namespace App\Module\Org\DTO;

// deze dto vangt het nieuwe werkpakket formulier op
class WorkPackageFormDTO
{
    public ?string $title = null;
    public ?string $slug = null;
    public ?string $description = null;
    public ?\DateTimeImmutable $dueDate = null;
}
