<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

/** Document a travel cost section belongs to (migration 182). */
enum TravelCostDocumentType: string
{
    case Offer = 'offer';
    case OrderConfirmation = 'order_confirmation';
    case Invoice = 'invoice';

    /** Column of document_travel_costs that references the document. */
    public function column(): string
    {
        return match ($this) {
            self::Offer => 'offer_id',
            self::OrderConfirmation => 'order_confirmation_id',
            self::Invoice => 'invoice_id',
        };
    }

    /** URL path segment of the document pages. */
    public function pathSegment(): string
    {
        return match ($this) {
            self::Offer => 'angebote',
            self::OrderConfirmation => 'auftragsbestaetigungen',
            self::Invoice => 'rechnungen',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Offer => 'Angebot',
            self::OrderConfirmation => 'Auftragsbestätigung',
            self::Invoice => 'Rechnung',
        };
    }
}
