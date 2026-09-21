<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Session;

/**
 * Single choke point for "which school is the current request scoped to".
 * A super_admin has no institution of their own — they act on a school only
 * after picking one via the "manage as" switcher, which stores it separately
 * so it never collides with a real staff member's home institution.
 */
class InstitutionContext
{
    public static function id(): ?int
    {
        if (Session::get('role') === 'super_admin') {
            $acting = Session::get('acting_institution_id');
            return $acting !== null ? (int) $acting : null;
        }

        $institutionId = Session::get('institution_id');
        return $institutionId !== null ? (int) $institutionId : null;
    }

    public static function isSuperAdmin(): bool
    {
        return Session::get('role') === 'super_admin';
    }
}
