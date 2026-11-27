<?php

namespace App\Module\Main\DTO;

enum ProjectApplyStatus: string
{
    case Success = 'success';
    case AlreadyApplied = 'already_applied';
    case AlreadyParticipant = 'already_participant';
    case NotOpen = 'not_open';
    case NoProfile = 'no_profile';
    case StatusNotFound = 'status_not_found';
}
