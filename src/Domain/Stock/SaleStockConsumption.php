<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use DateTimeImmutable;

/**
 * Protokolliert, welche Menge einer SaleStockLot-Charge zu welchem Preis von
 * einem bestimmten Verkauf (App\Domain\Sale\Sale) verbraucht wurde — ein
 * Bestandsverkauf kann mehrere Chargen anbrechen, wenn die älteste Charge
 * die verkaufte Menge nicht allein deckt. Rein für Nachvollziehbarkeit/Audit;
 * die eigentliche Margen-Summe steht gesnapshotet auf Sale::$costOfGoodsSold.
 */
final class SaleStockConsumption
{
    public function __construct(
        public readonly string $id,
        public readonly string $saleId,
        public readonly string $stockLotId,
        public readonly int $quantity,
        public readonly string $unitPurchasePrice,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
