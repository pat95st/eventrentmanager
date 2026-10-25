<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Manufacturer\Manufacturer;

final class ReceiptInventoryNumberEntry
{
    public function __construct(
        public readonly InventoryNumber $inventoryNumber,
        public readonly ?Article $article,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für die Belegzuordnung.
    public function displayName(): ?string
    {
        return $this->article !== null ? ArticleDisplayName::of($this->article, $this->manufacturer) : null;
    }
}
