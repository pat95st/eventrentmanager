<?php

declare(strict_types=1);

namespace App\Application\Sale\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Customer\Customer;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Sale\Sale;

/**
 * Ein Eintrag der Verkaufsübersicht — fasst einen Verkauf (egal ob aus dem
 * Vermietpool via Inventurnummer oder aus dem reinen Verkaufsbestand, siehe
 * Sale::isFromStock()) mit dem dazugehörigen Artikel und Kunden zusammen.
 */
final class SaleListEntry
{
    public function __construct(
        public readonly Sale $sale,
        public readonly ?Article $article,
        public readonly ?Customer $customer,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für die Verkaufsübersicht. Liefert "–", wenn
    // der Artikel zwischenzeitlich gelöscht wurde (siehe $article).
    public function displayName(): string
    {
        return $this->article !== null ? ArticleDisplayName::of($this->article, $this->manufacturer) : '–';
    }
}
