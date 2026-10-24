<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\ReceiptInbox\ReceiptInboxAttachmentRepositoryInterface;

/**
 * Statischer, request-gebundener Helfer für das kleine Benachrichtigungs-
 * Badge im Menüband (siehe layout.php), analog im Aufbau zu CurrentUser/
 * Flash. Wird NUR für angemeldete Benutzer mit dem passenden Recht
 * aufgerufen (siehe layout.php) und fragt die Datenbank dabei höchstens
 * einmal pro Request ab (statisches Cache-Property), statt die komplette
 * Command-/Query-Bus-Infrastruktur für eine einzelne COUNT(*)-Abfrage auf
 * jeder Seite aufzublähen.
 */
final class ReceiptInboxBadge
{
    private static ?int $cachedPendingCount = null;
    private static ?ReceiptInboxAttachmentRepositoryInterface $repository = null;

    public static function initialize(ReceiptInboxAttachmentRepositoryInterface $repository): void
    {
        self::$repository = $repository;
        self::$cachedPendingCount = null;
    }

    public static function pendingCount(): int
    {
        if (self::$cachedPendingCount !== null) {
            return self::$cachedPendingCount;
        }

        if (self::$repository === null) {
            return 0;
        }

        self::$cachedPendingCount = self::$repository->countPending();

        return self::$cachedPendingCount;
    }
}
