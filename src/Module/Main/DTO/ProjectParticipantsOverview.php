<?php

namespace App\Module\Main\DTO;

use App\Entity\Project\Project;
use App\Entity\Project\ProjectParticipant;

final readonly class ProjectParticipantsOverview
{
    /**
     * @param list<ProjectParticipant> $participants
     */
    public function __construct(
        public Project $project,
        public array $participants = [],
    ) {
    }
}