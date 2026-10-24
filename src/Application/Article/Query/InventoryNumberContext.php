<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Domain\Article\Article;
use App\Domain\Article\FirmwareVersion;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Manufacturer\Manufacturer;

final class InventoryNumberContext
{
    public function __construct(
        public readonly InventoryNumber $inventoryNumber,
        public readonly Article $article,
        public readonly Manufacturer $manufacturer,
        // Nur gesetzt, wenn article->hasFirmware UND eine installierte Version hinterlegt wurde.
        public readonly ?FirmwareVersion $installedFirmwareVersion = null,
        // true, wenn installedFirmwareVersion gesetzt ist und NICHT der aktuellsten Katalog-Version entspricht.
        public readonly bool $firmwareOutdated = false,
    ) {
    }
}
