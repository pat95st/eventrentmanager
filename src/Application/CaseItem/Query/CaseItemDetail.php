<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Domain\CaseItem\CaseItem;
use App\Domain\Manufacturer\Manufacturer;

final class CaseItemDetail
{
    /** @param CaseInventoryNumberWithContent[] $inventoryNumbers */
    public function __construct(
        public readonly CaseItem $case,
        public readonly Manufacturer $manufacturer,
        public readonly array $inventoryNumbers,
        public readonly ?string $nextInventoryNumberPreview,
    ) {
    }
}
