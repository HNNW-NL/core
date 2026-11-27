<?php

namespace App\Security;

use App\Entity\Project\Project;
use App\Module\Org\Service\ProjectLifecycle;
use Doctrine\ORM\QueryBuilder;

final class ProjectVisibility
{
    public static function isPubliclyVisible(Project $project): bool
    {
        return $project->getDeletedAt() === null
            && $project->getOwnerOrganisation() !== null
            && $project->getOwnerOrganisation()->getDeletedAt() === null
            && strcasecmp((string) $project->getStatus()?->getScope(), 'project') === 0
            && ProjectLifecycle::isPubliclyVisible($project);
    }

    public static function acceptsApplications(Project $project): bool
    {
        return self::isPubliclyVisible($project) && ProjectLifecycle::acceptsApplications($project);
    }

    public static function restrictQuery(QueryBuilder $query, string $alias): QueryBuilder
    {
        return $query->innerJoin($alias.'.status', $alias.'LifecycleStatus')
            ->innerJoin($alias.'.ownerOrganisation', $alias.'OwnerOrganisation')
            ->andWhere($alias.'.deletedAt IS NULL')->andWhere($alias.'.publishedAt IS NOT NULL')
            ->andWhere($alias.'.visibility = :'.$alias.'PublicVisibility')->setParameter($alias.'PublicVisibility', 'public')
            ->andWhere($alias.'OwnerOrganisation.deletedAt IS NULL')
            ->andWhere('LOWER('.$alias.'LifecycleStatus.scope) = :'.$alias.'LifecycleScope')->setParameter($alias.'LifecycleScope', 'project')
            ->andWhere('LOWER('.$alias.'LifecycleStatus.name) IN (:'.$alias.'PublicStatuses)')
            ->setParameter($alias.'PublicStatuses', ProjectLifecycle::PUBLIC_STATUSES);
    }
}
