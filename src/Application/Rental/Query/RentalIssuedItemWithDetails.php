<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Rental\RentalIssuedItem;

final class RentalIssuedItemWithDetails
{
    public function __construct(
        public readonly RentalIssuedItem $issuedItem,
        public readonly InventoryNumber $inventoryNumber,
        public readonly Article $article,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für Checklisten, Ladeliste und Packliste.
    public function displayName(): string
    {
        return ArticleDisplayName::of($this->article, $this->manufacturer);
    }
}
