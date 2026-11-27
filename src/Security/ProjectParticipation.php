<?php

namespace App\Security;

use App\Entity\Project\ProjectParticipant;
use Doctrine\ORM\QueryBuilder;

final class ProjectParticipation
{
    public const SCOPE = 'projectparticipant';
    public const ACTIVE_NAMES = ['active', 'actief'];

    public static function isActive(ProjectParticipant $participant): bool
    {
        return $participant->getJoinedAt() !== null && $participant->getLeftAt() === null
            && strtolower((string) $participant->getStatus()?->getScope()) === self::SCOPE
            && in_array(strtolower((string) $participant->getStatus()?->getName()), self::ACTIVE_NAMES, true);
    }

    public static function restrictQuery(QueryBuilder $query, string $alias): QueryBuilder
    {
        return $query->innerJoin($alias.'.status', $alias.'Status')
            ->innerJoin($alias.'.profile', $alias.'Profile')->innerJoin($alias.'Profile.account', $alias.'Account')
            ->andWhere(self::predicate($alias, $alias.'Status'))
            ->andWhere($alias.'Profile.deletedAt IS NULL AND '.$alias.'Account.deletedAt IS NULL');
    }

    public static function predicate(string $participant, string $status): string
    {
        $names = implode(', ', array_map(static fn (string $name): string => "'".$name."'", self::ACTIVE_NAMES));
        return $participant.'.joinedAt IS NOT NULL AND '.$participant.'.leftAt IS NULL'
            ." AND LOWER(".$status.".scope) = '".self::SCOPE."' AND LOWER(".$status.'.name) IN ('.$names.')';
    }
}
