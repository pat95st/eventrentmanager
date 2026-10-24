<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

/**
 * Alle Dokumenttypen, die eine fortlaufende, admin-konfigurierbare Nummer
 * erhalten (siehe DocumentNumberingSettings/DocumentNumberSequence). Der
 * String-Wert ist zugleich der Primärschlüssel in document_numbering_settings
 * und document_number_sequences — bewusst stabile, sprechende Werte statt
 * numerischer IDs, damit eine Migration sie direkt lesbar seeden kann.
 */
enum NumberedDocumentType: string
{
    case Invoice = 'invoice';
    case Offer = 'offer';
    case DunningNotice = 'dunning_notice';
    case OrderConfirmation = 'order_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Rechnungen',
            self::Offer => 'Angebote',
            self::DunningNotice => 'Mahnungen',
            self::OrderConfirmation => 'Auftragsbestätigungen',
        };
    }

    /** @return NumberedDocumentType[] Feste Anzeigereihenfolge auf der Einstellungsseite. */
    public static function all(): array
    {
        return [self::Invoice, self::Offer, self::DunningNotice, self::OrderConfirmation];
    }
}
