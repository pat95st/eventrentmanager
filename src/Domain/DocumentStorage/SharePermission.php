<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/** Was der Kunde über den Freigabe-Link eines Ordners darf (Nextcloud/ownCloud, Migration 148). */
enum SharePermission: string
{
    case Read = 'read';
    case Edit = 'edit';
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Nur ansehen und herunterladen',
            self::Edit => 'Ansehen, hochladen und bearbeiten',
            self::Upload => 'Nur hochladen (Dateiablage, Inhalt bleibt verborgen)',
        };
    }

    /** Berechtigungs-Bitmaske der OCS-Share-API: 1 lesen, 2 ändern, 4 anlegen, 8 löschen. */
    public function ocsPermissions(): int
    {
        return match ($this) {
            self::Read => 1,
            self::Edit => 15,
            self::Upload => 4,
        };
    }
}
