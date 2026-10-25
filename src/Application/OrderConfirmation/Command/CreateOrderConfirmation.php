<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandInterface;

final class CreateOrderConfirmation implements CommandInterface
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string}> $items
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $freeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $sectionTexts
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $eventLocation,
        public readonly string $note,
        public readonly array $items,
        public readonly bool $accessoryFlatRateEnabled = true,
        public readonly ?string $discountType = null,
        public readonly ?string $discountValue = null,
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
        // Rohes Rich-Text-HTML aus dem Formular, wird vom Handler über
        // HtmlSanitizer bereinigt (siehe OrderConfirmation::$closingText).
        public readonly ?string $closingText = null,
    ) {
    }
}
