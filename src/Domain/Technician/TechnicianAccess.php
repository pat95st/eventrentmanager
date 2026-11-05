<?php

declare(strict_types=1);

namespace App\Domain\Technician;

use App\Domain\User\Permission;
use App\Domain\User\User;

/**
 * Regeln für das Recht "Techniker" (Migration 134). Ein Benutzer gilt als
 * "nur Techniker", wenn er das Recht Techniker ausdrücklich hat, aber weder
 * Administration noch Ausgabe/Rücknahme — dann sieht er ausschließlich
 * Veranstaltungen, für die er angefragt oder eingeplant ist, und keine
 * Abrechnungsdaten.
 */
final class TechnicianAccess
{
    public static function isTechnician(User $user): bool
    {
        return in_array(Permission::Techniker, $user->permissions, true);
    }

    public static function isRestricted(?User $user): bool
    {
        return $user !== null
            && self::isTechnician($user)
            && !$user->isAdministrator()
            && !$user->has(Permission::AusgabeRuecknahme);
    }

    /** Darf $user (nur Techniker) die Veranstaltung sehen? */
    public static function canSeeRental(?RentalTechnician $assignment): bool
    {
        return $assignment !== null && $assignment->status->grantsAccess();
    }
}
