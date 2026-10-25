<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Manufacturer\Manufacturer;

final class InvoiceItemWithArticle
{
    public function __construct(
        public readonly InvoiceItem $item,
        public readonly Article $article,
        // Nullbar statt exception-werfend (siehe ArticleListItem für die
        // identische Begründung) — bei einem gelöschten/inkonsistenten
        // Hersteller fällt displayName() auf die reine Artikelbezeichnung
        // zurück statt die ganze Rechnungsansicht scheitern zu lassen.
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }

    // "Hersteller Artikelbezeichnung" (z. B. "Yamaha CL5") für Anzeige in
    // der Rechnungsansicht und im Rechnungs-PDF (Nutzerwunsch: Hersteller
    // immer vor der Artikelbezeichnung).
    public function displayName(): string
    {
        return ArticleDisplayName::of($this->article, $this->manufacturer);
    }
}
