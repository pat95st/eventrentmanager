<?php

declare(strict_types=1);

namespace App\Application\Sale\Command;

use App\Application\Command\CommandInterface;

/**
 * Verkauft eine Menge aus dem reinen Verkaufsbestand eines Artikels (NEUE
 * Ware ohne eigene Inventurnummer, siehe App\Domain\Stock\SaleStockLot) —
 * im Unterschied zu SellInventoryNumber, das ein konkretes, gebrauchtes
 * Exemplar aus dem Vermietpool verkauft. $salePrice ist der Gesamtpreis
 * (netto) für alle $quantity Einheiten (nicht der Einzelpreis).
 */
final class SellArticleFromStock implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $quantity, // Rohwert aus dem Formular
        public readonly ?string $customerId,
        public readonly string $salePrice, // Gesamtpreis (netto) für alle $quantity Einheiten
        public readonly ?string $shippingAmount,
        public readonly ?string $note,
    ) {
    }
}
