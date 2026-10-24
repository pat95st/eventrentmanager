<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandInterface;

/**
 * Erfasst einen (ggf. Teil-)Wareneingang für eine Bestellposition.
 * $quantity ist die Menge DIESER Lieferung (nicht die kumulierte Gesamtmenge)
 * — Teillieferungen sind möglich, solange die Summe aller Wareneingänge die
 * bestellte Menge nicht übersteigt (siehe ReceivePurchaseOrderItemHandler).
 */
final class ReceivePurchaseOrderItem implements CommandInterface
{
    public function __construct(
        public readonly string $purchaseOrderItemId,
        public readonly string $quantity, // Rohwert aus dem Formular
    ) {
    }
}
