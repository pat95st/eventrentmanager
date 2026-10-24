<?php

declare(strict_types=1);

namespace App\Domain\OrderConfirmation;

/**
 * Ein frei formulierbarer Posten ohne Artikelbezug (z. B. eine Pauschale
 * oder ein Hinweistext mit Preis) — Teil des orderedRows()-Konzepts (siehe
 * App\Application\OrderConfirmation\Query\GetOrderConfirmationHandler):
 * reguläre Positionen, Freitext-Posten und Zwischentexte (nur
 * Überschriften, siehe OrderConfirmationSectionText) teilen sich über
 * $sortOrder eine gemeinsame, frei wählbare Reihenfolge.
 */
final class OrderConfirmationFreeTextItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $orderConfirmationId,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public int $sortOrder = 0,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPrice) * ((float) $this->quantity);
    }
}
