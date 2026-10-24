<?php

declare(strict_types=1);

namespace App\Domain\Offer;

/** Eine reine Zwischenüberschrift ohne Preis — siehe OfferFreeTextItem für das gemeinsame orderedRows()-Konzept. */
final class OfferSectionText
{
    public function __construct(
        public readonly string $id,
        public readonly string $offerId,
        public string $heading,
        public int $sortOrder = 0,
    ) {
    }
}
