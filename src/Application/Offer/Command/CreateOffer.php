<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandInterface;

final class CreateOffer implements CommandInterface
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string, sort_order?: string}> $items
     * @param array<int, array{labor_article_id: string, hours: string}> $laborItems
     * @param array<int, array{article_id: string, source: string, inventory_number_id: string, quantity: string, unit_price: string, line_note: string}> $saleItems
     * @param array<int, array{description: string, quantity: string, unit_price: string, sort_order?: string}> $freeTextItems
     * @param array<int, array{heading: string, sort_order?: string}> $sectionTexts
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $startDate, // 'Y-m-d'
        public readonly string $endDate,   // 'Y-m-d'
        public readonly string $eventLocation, // Freitext; wird im Handler ggf. einem gespeicherten Veranstaltungsort zugeordnet
        public readonly string $validUntil, // 'Y-m-d', optional (leer = kein Ablaufdatum)
        public readonly string $note,
        public readonly array $items,
        public readonly bool $accessoryFlatRateEnabled = true,
        public readonly ?string $discountType = null, // 'percent'|'fixed'|null
        public readonly ?string $discountValue = null,
        public readonly array $laborItems = [],
        public readonly array $saleItems = [],
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
        // Rohes Rich-Text-HTML aus dem Formular, wird vom Handler über
        // HtmlSanitizer bereinigt (siehe Offer::$closingText).
        public readonly ?string $closingText = null,
        // Optionale Verknüpfung mit einer bestehenden Veranstaltung (Rental)
        // über die Live-Suche im Formular — siehe Offer::$sourceRentalId.
        public readonly ?string $rentalId = null,
        // Optionale Auswahl eines Ansprechpartners des Kunden, siehe
        // Offer::$contactId.
        public readonly ?string $contactId = null,
        // Zahlungsart (Migration 106), leer = Standard-Zahlungsart.
        public readonly ?string $paymentMethodId = null,
    ) {
    }
}
