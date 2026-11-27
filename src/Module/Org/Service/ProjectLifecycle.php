<?php

namespace App\Module\Org\Service;

use App\Entity\Project\Project;

/**
 * Task mutation contract from Team 1 PR #93, revision 873bf61b.
 * Only its shared mutation API is needed here; project publication and
 * lifecycle transitions remain owned by PR #93.
 */
final class ProjectLifecycle
{
    public static function allowsTaskChanges(Project $project): bool
    {
        return self::hasValidProjectContext($project)
            && in_array(strtolower((string) $project->getStatus()?->getName()), ['active', 'draft', 'published', 'applications_closed'], true);
    }

    private static function hasValidProjectContext(Project $project): bool
    {
        $organisation = $project->getOwnerOrganisation();

        return $project->getDeletedAt() === null
            && $organisation !== null
            && $organisation->getDeletedAt() === null
            && $project->getStatus()?->getScope() === 'project';
    }

    public static function assertTaskChangesAllowed(Project $project): void
    {
        if (!self::allowsTaskChanges($project)) {
            throw new \InvalidArgumentException('The current project state does not allow this change.');
        }
    }
}
