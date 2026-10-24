<?php

declare(strict_types=1);

namespace App\Domain\OrderConfirmation;

/** Eine reguläre Mietpositions-Zeile — strukturell identisch zu App\Domain\Offer\OfferItem, siehe dort. */
final class OrderConfirmationItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $orderConfirmationId,
        public readonly string $articleId,
        public int $quantity,
        public string $unitPricePerDay,
        public ?string $lineNote,
        public int $sortOrder = 0,
    ) {
    }

    public function lineTotal(): float
    {
        return ((float) $this->unitPricePerDay) * $this->quantity;
    }
}
