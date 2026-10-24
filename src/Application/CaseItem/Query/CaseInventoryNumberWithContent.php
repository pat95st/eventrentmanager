<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Domain\CaseInventoryNumber\CaseInventoryNumber;

final class CaseInventoryNumberWithContent
{
    /** @param CaseContentItemWithArticle[] $content */
    public function __construct(
        public readonly CaseInventoryNumber $inventoryNumber,
        public readonly array $content,
    ) {
    }
}
