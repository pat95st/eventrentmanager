<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Infrastructure\Http\Auth\Session;

/**
 * Odometer reading confirmed on the check page of a vehicle (migration 185),
 * remembered in the session for VALID_SECONDS so the capture form can start
 * with it. The capture handler checks the reading again.
 */
final class LogbookOdometerSession
{
    private const KEY = 'logbook_odometer';
    private const VALID_SECONDS = 7200;

    public static function remember(string $vehicleId, int $reading): void
    {
        Session::ensureStarted();
        $_SESSION[self::KEY][$vehicleId] = ['km' => $reading, 'at' => time()];
    }

    public static function confirmed(string $vehicleId): ?int
    {
        Session::ensureStarted();
        $entry = $_SESSION[self::KEY][$vehicleId] ?? null;
        if (!is_array($entry) || (int) ($entry['at'] ?? 0) < time() - self::VALID_SECONDS) {
            return null;
        }

        return (int) $entry['km'];
    }

    public static function forget(string $vehicleId): void
    {
        Session::ensureStarted();
        unset($_SESSION[self::KEY][$vehicleId]);
    }
}
