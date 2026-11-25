<?php

namespace App\Module\Org\DTO;

// deze dto vangt het nieuwe taak formulier op
// er staat er een per werkpakket op de pagina, daarom houdt hij bij bij welk werkpakket hij hoort
class PackageTaskFormDTO
{
    public ?string $workPackageId = null;
    public ?string $taskTitle = null;
    public ?string $taskSlug = null;
    public ?string $taskDescription = null;
    public ?\DateTimeImmutable $taskDueDate = null;
    public ?string $taskPriority = 'normal';

    public function __construct(?string $workPackageId = null)
    {
        $this->workPackageId = $workPackageId;
    }
}
