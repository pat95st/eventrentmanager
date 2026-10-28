<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandInterface;

final class CreateInvoice implements CommandInterface
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string, is_rental_item?: string}> $items
     * @param array<int, array{labor_article_id: string, hours: string}> $laborItems
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $freeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $sectionTexts
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $startDate, // 'Y-m-d'
        public readonly string $endDate,   // 'Y-m-d'
        public readonly string $eventLocation,
        public readonly string $buyerReference,
        public readonly string $note,
        public readonly array $items,
        public readonly bool $accessoryFlatRateEnabled = true,
        public readonly ?string $discountType = null, // 'percent'|'fixed'|null
        public readonly ?string $discountValue = null,
        public readonly array $laborItems = [],
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
        // Rohes Rich-Text-HTML aus dem Formular, wird vom Handler über
        // HtmlSanitizer bereinigt (siehe Invoice::$closingText).
        public readonly ?string $closingText = null,
        // Optionale Verknüpfung mit einer bestehenden Veranstaltung
        // (Rental) über die Live-Suche im Formular — siehe Invoice::$sourceRentalId.
        public readonly ?string $rentalId = null,
        // Optionale Auswahl eines Ansprechpartners des Kunden, siehe
        // Invoice::$contactId.
        public readonly ?string $contactId = null,
        // Pauschalpreis für die Mietpositionen, siehe Invoice::$flatRateEnabled.
        public readonly bool $flatRateEnabled = false,
        public readonly ?string $flatRateAmount = null,
        // Logistik bei Vermietungen, siehe Invoice::$logisticsMethod.
        public readonly ?string $logisticsMethod = null,
        public readonly ?string $logisticsDate1 = null,
        public readonly ?string $logisticsDate2 = null,
    ) {
    }
}
