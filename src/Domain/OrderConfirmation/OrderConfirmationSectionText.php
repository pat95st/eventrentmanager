<?php

declare(strict_types=1);

namespace App\Domain\OrderConfirmation;

/** Eine reine Zwischenüberschrift ohne Preis — siehe OrderConfirmationFreeTextItem für das gemeinsame orderedRows()-Konzept. */
final class OrderConfirmationSectionText
{
    public function __construct(
        public readonly string $id,
        public readonly string $orderConfirmationId,
        public string $heading,
        public int $sortOrder = 0,
    ) {
    }
}
