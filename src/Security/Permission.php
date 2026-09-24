<?php

namespace App\Security;

enum Permission: int
{
    // Admin scope (platformbreed, via Admin/AdminRole) ---
    case ADMIN_VIEW_DASHBOARD       = 1 << 0;
    case ADMIN_MANAGE_ACCOUNTS      = 1 << 1;
    case ADMIN_MANAGE_ORGANISATIONS = 1 << 2;
    case ADMIN_VIEW_LOGS            = 1 << 3;
    case ADMIN_MANAGE_SETTINGS      = 1 << 4;
    case ADMIN_MANAGE_STAFF         = 1 << 5;

    // --- Organisation scope (per organisatie, via OrgMember/OrgRole) ---
    case ORG_VIEW            = 1 << 10;
    case ORG_EDIT             = 1 << 11;
    case ORG_MANAGE_MEMBERS   = 1 << 12;
    case ORG_MANAGE_ROLES     = 1 << 13;
    case ORG_MANAGE_PROJECTS  = 1 << 14;
    case ORG_DELETE           = 1 << 15;

    /**
     * omschrijving, handig voor bijvoorbeeld een rollenbeheer-scherm*/
    public function label(): string
    {
        return match ($this) {
            self::ADMIN_VIEW_DASHBOARD => 'Admin dashboard bekijken',
            self::ADMIN_MANAGE_ACCOUNTS => 'Accounts beheren',
            self::ADMIN_MANAGE_ORGANISATIONS => 'Organisaties beheren',
            self::ADMIN_VIEW_LOGS => 'Systeemlogs bekijken',
            self::ADMIN_MANAGE_SETTINGS => 'Platforminstellingen beheren',
            self::ADMIN_MANAGE_STAFF => 'Social/staff beheren',
            self::ORG_VIEW => 'Organisatie bekijken',
            self::ORG_EDIT => 'Organisatie aanpassen',
            self::ORG_MANAGE_MEMBERS => 'Leden beheren',
            self::ORG_MANAGE_ROLES => 'Rollen beheren',
            self::ORG_MANAGE_PROJECTS => 'Projecten beheren',
            self::ORG_DELETE => 'Organisatie verwijderen',
        };
    }

    /**
     * Zoekt een Permission op basis van de enum-naam (bijv. "ORG_EDIT").
     * Dit is nodig omdat de Voters een string-attribute binnenkrijgen
     * (zoals bij IsGranted('ORG_EDIT', $organisation))..
     */
    public static function fromName(string $name): ?self
    {
        foreach (self::cases() as $permission) {
            if ($permission->name === $name) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * Telt een lijst permissions op tot één mask, om op te slaan in
     * AdminRole/OrgRole::setPermissionsMask().
     *
     *  @param Permission[] $permissions
     */
    public static function maskFor(array $permissions): int
    {
        $mask = 0;
        foreach ($permissions as $permission) {
            $mask |= $permission->value;
        }

        return $mask;
    }

    /**
     * Controleert of deze permission aanwezig is in een gegeven mask.*/
    public function in(?int $mask): bool
    {
        if ($mask === null) {
            return false;
        }

        return ($mask & $this->value) === $this->value;
    }
}