<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

use App\Domain\User\Permission;
use App\Domain\User\User;
use App\Domain\Vehicle\Vehicle;
use DateTimeImmutable;

/**
 * Access rules of the driver's logbook (migrations 183/184):
 * - "Fahrer" records and sees own trips, "Fahrtenbuch verwalten" sees and
 *   edits all trips (administrators have both).
 * - Private vehicles can only be used by their owner and logbook managers.
 * - Drivers may edit or delete their own trips for LogbookSettings::$editWindowDays
 *   after recording; trips of external drivers only managers can edit.
 */
final class LogbookAccess
{
    public static function canCapture(?User $user): bool
    {
        return $user !== null && ($user->has(Permission::Fahrer) || $user->has(Permission::FahrtenbuchVerwalten));
    }

    public static function canManage(?User $user): bool
    {
        return $user !== null && $user->has(Permission::FahrtenbuchVerwalten);
    }

    public static function canUseVehicle(?User $user, Vehicle $vehicle): bool
    {
        if (!self::canCapture($user)) {
            return false;
        }

        return !$vehicle->isPrivate || self::canManage($user) || $vehicle->isOwnedBy($user?->id);
    }

    public static function canView(?User $user, LogbookEntry $entry): bool
    {
        return self::canManage($user) || (self::canCapture($user) && $entry->belongsTo($user?->id));
    }

    public static function canEdit(?User $user, LogbookEntry $entry, LogbookSettings $settings, ?DateTimeImmutable $now = null): bool
    {
        if (self::canManage($user)) {
            return true;
        }
        if (!self::canCapture($user) || !$entry->belongsTo($user?->id) || $entry->isDeleted()) {
            return false;
        }
        $deadline = $entry->createdAt->modify('+' . max(0, $settings->editWindowDays) . ' days');

        return ($now ?? new DateTimeImmutable()) <= $deadline;
    }
}
