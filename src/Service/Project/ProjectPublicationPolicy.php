<?php

namespace App\Service\Project;

use App\Entity\Project\Project;

final class ProjectPublicationPolicy
{
    public const NON_PUBLIC_STATUSES = ['draft', 'archived'];
    public const APPLICATION_STATUSES = ['active', 'published'];

    public static function isPublished(Project $project): bool
    {
        $status = strtolower(trim((string) $project->getStatus()?->getName()));

        return $project->getPublishedAt() !== null
            && $status !== ''
            && !in_array($status, self::NON_PUBLIC_STATUSES, true);
    }

    public static function isPublic(Project $project): bool
    {
        $organisation = $project->getOwnerOrganisation();

        return self::isPublished($project)
            && $project->getVisibility() === 'public'
            && $project->getDeletedAt() === null
            && $organisation !== null
            && $organisation->getDeletedAt() === null;
    }
}
