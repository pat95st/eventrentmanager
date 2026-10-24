<?php

declare(strict_types=1);

namespace App\Application\Numbering\Query;

use App\Domain\Numbering\DocumentNumberingSettings;

/**
 * Eine Zeile der Einstellungsseite "Nummerierung" für EINEN Dokumenttyp:
 * die gespeicherten Einstellungen plus eine Vorschau der nächsten zu
 * vergebenden Nummer (rein informativ, keine Reservierung).
 */
final class NumberingSettingsOverviewEntry
{
    public function __construct(
        public readonly DocumentNumberingSettings $settings,
        public readonly int $previewYear,
        public readonly int $nextNumber,
        public readonly string $previewFormatted,
    ) {
    }
}
