<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Domain\CaseInventoryNumber\CaseInventoryNumber;

final class CaseInventoryNumberWithContent
{
    /**
     * @param CaseContentItemWithArticle[] $content
     * @param \App\Domain\CaseInventoryNumber\CaseInventoryNumberImage[] $images Fotos des befüllten Exemplars (Migration 114)
     */
    public function __construct(
        public readonly CaseInventoryNumber $inventoryNumber,
        public readonly array $content,
        public readonly array $images = [],
    ) {
    }
}
