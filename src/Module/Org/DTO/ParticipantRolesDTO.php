<?php

namespace App\Module\Org\DTO;

// deze dto vangt de rollen van alle deelnemers op
// de sleutel van de array is het id van de deelnemer, de waarde is het gekozen rol-id
class ParticipantRolesDTO
{
    public array $role = [];
}
