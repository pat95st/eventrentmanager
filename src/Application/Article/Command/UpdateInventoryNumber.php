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
    ) {
    }
}
