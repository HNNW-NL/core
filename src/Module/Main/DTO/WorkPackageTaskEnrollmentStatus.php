<?php

namespace App\Module\Main\DTO;

enum WorkPackageTaskEnrollmentStatus: string
{
    case Success = 'success';
    case AlreadyAssignedToCurrentProfile = 'already_assigned_to_current_profile';
    case AlreadyAssignedToOtherProfile = 'already_assigned_to_other_profile';
    case NotParticipant = 'not_participant';
    case TaskNotFound = 'task_not_found';
}