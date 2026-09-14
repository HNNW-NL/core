<?php

namespace App\Module\AccountCentre\DTO;

// deze dto vangt het beschikbaarheid formulier op
// het weekrooster van zeven dagen past niet in de availability tabel, die heeft er geen kolommen voor
class AvailabilityDTO
{
    public ?string $availabilityType = 'TidyCal Schema';
    public ?int $hoursPerWeek = 40;
    public ?\DateTimeImmutable $startDate = null;

    // zeven dagen, maandag tot en met zondag
    public array $daysConfig = [];

    public function __construct()
    {
        $this->startDate = new \DateTimeImmutable();

        for ($dag = 0; $dag < 7; $dag++) {
            $this->daysConfig[$dag] = new DayAvailabilityDTO();
        }
    }
}
