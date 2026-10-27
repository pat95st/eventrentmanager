<?php

declare(strict_types=1);

namespace App\Domain\Numbering;

/**
 * Alle Dokumenttypen, die eine fortlaufende, admin-konfigurierbare Nummer
 * erhalten (siehe DocumentNumberingSettings/DocumentNumberSequence). Der
 * String-Wert ist zugleich der Primärschlüssel in document_numbering_settings
 * und document_number_sequences — bewusst stabile, sprechende Werte statt
 * numerischer IDs, damit eine Migration sie direkt lesbar seeden kann.
 *
 * `Customer` ist fachlich kein Dokument, sondern die Kundennummer (siehe
 * App\Domain\Customer\Customer::$customerNumber, Migration 095) — dieses
 * Nummerierungssystem war aber bereits vollständig generisch
 * (Einstellungsseite /einstellungen/nummerierung iteriert nur über all()),
 * weshalb die Kundennummer hier einfach als fünfter Fall mitläuft statt
 * eine eigene, parallele Lösung zu bekommen.
 */
enum NumberedDocumentType: string
{
    case Invoice = 'invoice';
    case Offer = 'offer';
    case DunningNotice = 'dunning_notice';
    case OrderConfirmation = 'order_confirmation';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Rechnungen',
            self::Offer => 'Angebote',
            self::DunningNotice => 'Mahnungen',
            self::OrderConfirmation => 'Auftragsbestätigungen',
            self::Customer => 'Kunden',
        };
    }

    /** @return NumberedDocumentType[] Feste Anzeigereihenfolge auf der Einstellungsseite. */
    public static function all(): array
    {
        return [self::Invoice, self::Offer, self::DunningNotice, self::OrderConfirmation, self::Customer];
    }
}
