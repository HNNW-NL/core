<?php

namespace App\Security;

use App\Entity\Project\Project;
use App\Module\Org\Service\ProjectLifecycle;
use Doctrine\ORM\QueryBuilder;

final class ProjectVisibility
{
    public static function isPubliclyVisible(Project $project): bool
    {
        return ProjectLifecycle::isPubliclyVisible($project);
    }

    public static function acceptsApplications(Project $project, ?int $activeParticipants = null): bool
    {
        return self::isPubliclyVisible($project) && ProjectLifecycle::acceptsApplications($project, $activeParticipants);
    }

    public static function restrictQuery(QueryBuilder $query, string $alias): QueryBuilder
    {
        return $query->innerJoin($alias.'.status', $alias.'LifecycleStatus')
            ->innerJoin($alias.'.ownerOrganisation', $alias.'OwnerOrganisation')
            ->andWhere($alias.'.deletedAt IS NULL')->andWhere($alias.'.publishedAt IS NOT NULL')
            ->andWhere($alias.'.visibility = :'.$alias.'PublicVisibility')->setParameter($alias.'PublicVisibility', 'public')
            ->andWhere($alias.'OwnerOrganisation.deletedAt IS NULL')
            ->andWhere($alias.'LifecycleStatus.scope = :'.$alias.'LifecycleScope')->setParameter($alias.'LifecycleScope', 'project')
            ->andWhere('LOWER('.$alias.'LifecycleStatus.name) IN (:'.$alias.'PublicStatuses)')
            ->setParameter($alias.'PublicStatuses', ProjectLifecycle::PUBLIC_STATUSES);
    }
}
