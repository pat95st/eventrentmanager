<?php

declare(strict_types=1);

namespace App\Application\Sale\Query;

use App\Domain\Article\Article;
use App\Domain\Customer\Customer;
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
    ) {
    }
}
