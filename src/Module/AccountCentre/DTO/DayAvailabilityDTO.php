<?php

namespace App\Module\AccountCentre\DTO;

// een enkele dag uit het weekrooster
// de tijden hebben seconden erbij, want zo wil symfony ze hebben als je een string gebruikt
class DayAvailabilityDTO
{
    public bool $enabled = false;
    public ?string $start = '08:00:00';
    public ?string $end = '17:00:00';
}
