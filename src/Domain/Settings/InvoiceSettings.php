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
    ) {
    }
}
