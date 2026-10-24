<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class AddInventoryNumber implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $acquisitionDate, // 'Y-m-d' oder null
        public readonly ?string $supplierId = null,
        public readonly ?string $receiptId = null,
        public readonly ?string $purchasePriceOverride = null,
        public readonly ?string $storageLocation = null,
        public readonly ?string $installedFirmwareVersionId = null,
    ) {
    }
}
