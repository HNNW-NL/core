<?php

namespace App\Module\Org\Service;

use App\Entity\Org\OrgRole;
use App\Entity\Org\Organisation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final class StaffOrganisationContext
{
    public const QUERY_KEY = 'organisation_id';
    public const SESSION_KEY = 'staff_organisation_id';

    public static function requestedId(Request $request): ?string
    {
        $value = $request->query->all()[self::QUERY_KEY] ?? null;

        return is_string($value) && Uuid::isValid($value)
            ? Uuid::fromString($value)->toRfc4122()
            : null;
    }

    public static function roleMask(?OrgRole $role, Organisation $organisation): int
    {
        if ((string) $role?->getOrganisation()?->getId() !== (string) $organisation->getId()) {
            return 0;
        }

        return $role?->getPermissionsMask() ?? 0;
    }
}
