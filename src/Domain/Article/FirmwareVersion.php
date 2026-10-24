<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

/**
 * Eine im Katalog eines Artikels hinterlegte Firmware-Version (z. B. "2.3.1").
 * Nur relevant, wenn Article::$hasFirmware gesetzt ist. Welche Version auf
 * einer konkreten Inventurnummer installiert ist, wird NICHT hier, sondern
 * auf InventoryNumber::$installedFirmwareVersionId gespeichert (Fremdschlüssel
 * auf diese Entität) — siehe InventoryNumber.
 */
final class FirmwareVersion
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public string $version, // Versionsbezeichnung, z. B. "2.3.1"; Freitext, da Hersteller-Schemata variieren
        public ?DateTimeImmutable $releasedAt, // Erscheinungs-/Anlagedatum laut Hersteller; optional, da oft unbekannt
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
