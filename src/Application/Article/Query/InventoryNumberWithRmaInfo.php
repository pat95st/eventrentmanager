<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Domain\Article\FirmwareVersion;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Sale\Sale;

final class InventoryNumberWithRmaInfo
{
    public function __construct(
        public readonly InventoryNumber $inventoryNumber,
        public readonly int $totalRmaCount,
        public readonly int $openRmaCount, // status "offen" oder "unterwegs"
        public readonly ?Sale $sale = null, // gesetzt, sobald dieses Exemplar verkauft wurde
        // Nur gesetzt, wenn der Artikel hasFirmware ist UND eine installierte Version hinterlegt wurde.
        public readonly ?FirmwareVersion $installedFirmwareVersion = null,
        // true, wenn installedFirmwareVersion gesetzt ist und NICHT der aktuellsten Katalog-Version entspricht.
        public readonly bool $firmwareOutdated = false,
    ) {
    }
}
