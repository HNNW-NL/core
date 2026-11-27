<?php

namespace App\Module\Main\DTO;

use App\Entity\Project\ProjectApplication;

final readonly class ProjectApplicationSubmission
{
    /** created denotes a newly submitted or restored application, rather than an unchanged duplicate. */
    public function __construct(public ProjectApplication $application, public bool $created)
    {
    }
}
