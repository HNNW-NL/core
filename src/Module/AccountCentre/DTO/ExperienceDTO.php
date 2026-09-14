<?php

namespace App\Module\AccountCentre\DTO;

// deze dto vangt het werkervaring formulier op
// de eerste zes velden staan wel in de database, de rest nog niet
class ExperienceDTO
{
    public ?string $jobTitle = null;
    public ?string $organisationName = null;
    public ?string $description = null;
    public ?\DateTimeImmutable $startDate = null;
    public ?\DateTimeImmutable $endDate = null;
    public bool $isCurrent = false;

    // deze velden hebben nog geen kolom, ze worden dus nog niet bewaard
    public ?string $employmentType = null;
    public ?string $location = null;
    public ?string $locationType = null;
    public ?string $profileHeadline = null;
    public ?string $vacancySource = null;
    public ?string $skills = null;
}
