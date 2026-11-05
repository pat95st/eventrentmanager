<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Firmenweite Einstellungen für die Rechnungsfunktion (Kleinunternehmer-
 * Regelung, Steuersatz, Steuernummer/USt-IdNr., Zahlungsziel,
 * Bankverbindung), Singleton-Zeile analog zu CompanySettings/
 * OfferPdfSettings. Das Rechnungsnummern-Präfix ist seit Einführung des
 * generischen Nummerierungssystems NICHT mehr hier, sondern unter
 * App\Domain\Numbering\DocumentNumberingSettings (Einstellungsseite
 * "Nummerierung", siehe DocumentNumberingSettingsController) gepflegt.
 * Was tatsächlich auf einer konkreten Rechnung steht, ist
 * NICHT dieser Live-Wert, sondern der beim Versenden (MarkInvoiceSentHandler)
 * auf die Rechnung SELBST gesnapshotete Wert (Invoice::$isSmallBusiness /
 * $vatRatePercent) — eine spätere Änderung dieser Einstellung darf eine
 * bereits versendete Rechnung nie rückwirkend verändern.
 */
final class InvoiceSettings
{
    public function __construct(
        public bool $isSmallBusiness, // Kleinunternehmer nach § 19 UStG
        public string $vatRatePercent, // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden (siehe OfferItem::$unitPricePerDay)
        public ?string $taxNumber, // Steuernummer
        public ?string $vatId, // USt-IdNr.
        public int $paymentTermDays,
        public ?string $bankName,
        public ?string $iban,
        public ?string $bic,
        // Migration 133: Firmendaten für Regelbesteuerung (Pflichtangaben auf
        // Geschäftsbriefen/Rechnungen) und anpassbarer Befreiungshinweis.
        public ?string $taxOffice = null,          // Finanzamt
        public ?string $legalForm = null,          // Rechtsform, z. B. GmbH
        public ?string $registerCourt = null,      // Registergericht, z. B. Amtsgericht Nürnberg
        public ?string $registerNumber = null,     // z. B. HRB 12345
        public ?string $managingDirectors = null,  // Geschäftsführung / Inhaber
        public ?string $smallBusinessNote = null,  // leer = DEFAULT_SMALL_BUSINESS_NOTE
    ) {
    }

    public const DEFAULT_SMALL_BUSINESS_NOTE = 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.';

    /** Hinweistext zur Umsatzsteuerbefreiung auf Dokumenten (eigener Text oder Standard). */
    public function smallBusinessNoteText(): string
    {
        $note = trim((string) $this->smallBusinessNote);

        return $note !== '' ? $note : self::DEFAULT_SMALL_BUSINESS_NOTE;
    }

    /** Registereintrag für die Fußzeile, z. B. "Amtsgericht Nürnberg, HRB 12345". */
    public function registerLine(): ?string
    {
        $parts = array_filter([trim((string) $this->registerCourt), trim((string) $this->registerNumber)], static fn ($v) => $v !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
