<?php

declare(strict_types=1);

namespace App\Domain\Purchase;

use DateTimeImmutable;

/**
 * Eine Bestellung bei einem Lieferanten — enthält nur die Kopfdaten; die
 * Positionen stehen in PurchaseOrderItem. Bewusst einfacher als Invoice/
 * Offer: keine fortlaufende Nummerierung, kein PDF-Export, kein Zeitraum.
 */
final class PurchaseOrder
{
    public function __construct(
        public readonly string $id,
        public string $supplierId,
        public PurchaseOrderStatus $status,
        public ?DateTimeImmutable $orderedAt,
        public ?string $note,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isEditable(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }
}
