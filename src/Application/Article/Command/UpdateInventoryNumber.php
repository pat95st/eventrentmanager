<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class UpdateInventoryNumber implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $acquisitionDate,
        public readonly ?string $supplierId = null,
        public readonly ?string $receiptId = null,
        public readonly ?string $purchasePriceOverride = null,
        public readonly ?string $storageLocation = null,
        public readonly ?string $installedFirmwareVersionId = null,
        // Migration 144: Zeitwert für die Versicherungsliste. Nur wenn
        // updateCurrentValue gesetzt ist (Feld war im Formular), wird er
        // übernommen; leer löscht ihn.
        public readonly ?string $currentValue = null,
        public readonly bool $updateCurrentValue = false,
    ) {
    }
}
