<?php

declare(strict_types=1);

namespace App\Application\Firmware\Query;

use App\Domain\Article\Article;
use App\Domain\Article\FirmwareVersion;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Manufacturer\Manufacturer;

/**
 * Eine Inventurnummer, auf der eine ältere Firmware-Version installiert ist
 * als die aktuellste im Artikel-Katalog hinterlegte — für die
 * artikelübergreifende "Firmware-Updates verfügbar"-Übersicht.
 */
final class FirmwareUpdateEntry
{
    public function __construct(
        public readonly InventoryNumber $inventoryNumber,
        public readonly Article $article,
        public readonly Manufacturer $manufacturer,
        public readonly FirmwareVersion $installedVersion,
        public readonly FirmwareVersion $latestVersion,
    ) {
    }
}
