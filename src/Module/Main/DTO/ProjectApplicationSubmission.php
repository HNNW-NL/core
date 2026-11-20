<?php

namespace App\Module\Main\DTO;

use App\Entity\Project\ProjectApplication;

final readonly class ProjectApplicationSubmission
{
    public function __construct(public ProjectApplication $application, public bool $created)
    {
    }
}
