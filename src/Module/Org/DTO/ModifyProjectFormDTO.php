<?php

namespace App\Module\Org\DTO;

// deze dto vangt het project wijzigen formulier op
// er bestaat al een ModifyProjectDTO, maar die heeft verplichte constructor argumenten
// en werkt daardoor niet als model voor een symfony form
class ModifyProjectFormDTO
{
    // deze drie stonden als verborgen velden in de pagina
    public ?string $projectId = null;
    public ?string $organisationId = null;
    public ?string $lastModified = null;

    public ?string $title = null;
    public ?string $summary = null;
    public ?string $description = null;
    public ?string $visibility = 'public';
    public ?\DateTimeImmutable $startDate = null;
    public ?\DateTimeImmutable $endDate = null;
    public ?int $capacity = null;
    public ?string $status = null;
}
