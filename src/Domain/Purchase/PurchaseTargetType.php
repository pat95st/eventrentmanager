<?php

declare(strict_types=1);

namespace App\Domain\Purchase;

/**
 * Wofür eine Bestellposition bei Wareneingang bestimmt ist — je Position
 * separat wählbar, ein Lieferant kann also in derselben Bestellung sowohl
 * Vermiet- als auch reine Verkaufsware liefern (siehe
 * ReceivePurchaseOrderItemHandler):
 * - RentalPool: beim Wareneingang wird für jede eingetroffene Einheit eine
 *   einzelne Inventurnummer angelegt (wie AddInventoryNumberHandler), das
 *   Exemplar landet im normalen Vermietbestand.
 * - SaleStock: beim Wareneingang wird eine neue Bestandscharge
 *   (App\Domain\Stock\SaleStockLot) angelegt — keine Inventurnummer, nur
 *   eine Mengenzählung je Artikel (FIFO bei späterem Verkauf, siehe
 *   SellArticleFromStockHandler). Diese Exemplare gelangen NIE in den
 *   Vermietpool.
 */
enum PurchaseTargetType: string
{
    case RentalPool = 'rental_pool';
    case SaleStock = 'sale_stock';

    public function label(): string
    {
        return match ($this) {
            self::RentalPool => 'Vermietpool (Inventurnummern)',
            self::SaleStock => 'Reiner Verkaufsbestand',
        };
    }
}
