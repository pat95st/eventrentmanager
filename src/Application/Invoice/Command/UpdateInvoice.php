<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandInterface;

final class UpdateInvoice implements CommandInterface
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string, is_rental_item?: string}> $items
     * @param array<int, array{labor_article_id: string, hours: string}> $laborItems
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $freeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $sectionTexts
     */
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $eventLocation,
        public readonly string $buyerReference,
        public readonly string $note,
        public readonly array $items,
        public readonly bool $accessoryFlatRateEnabled = true,
        public readonly ?string $discountType = null,
        public readonly ?string $discountValue = null,
        public readonly array $laborItems = [],
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
        // Rohes Rich-Text-HTML aus dem Formular, wird vom Handler über
        // HtmlSanitizer bereinigt (siehe Invoice::$closingText).
        public readonly ?string $closingText = null,
        public readonly ?string $rentalId = null,
        // Optionale Auswahl eines Ansprechpartners des Kunden, siehe
        // Invoice::$contactId.
        public readonly ?string $contactId = null,
        public readonly bool $flatRateEnabled = false,
        public readonly ?string $flatRateAmount = null,
        public readonly ?string $logisticsMethod = null,
        public readonly ?string $logisticsDate1 = null,
        public readonly ?string $logisticsDate2 = null,
        // Zahlungsart/Bankverbindung (Migrationen 105/106), leer = Standard.
        public readonly ?string $paymentMethodId = null,
        public readonly ?string $bankAccountId = null,
    ) {
    }
}
