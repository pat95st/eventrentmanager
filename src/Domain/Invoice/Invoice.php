<?php

declare(strict_types=1);

namespace App\Domain\Invoice;

use DateTimeImmutable;

/**
 * Eine Rechnung — bewusst eine EIGENE, von Rental und Offer unabhängige
 * Entität (dieselbe Produktentscheidung wie bei Offer), auch wenn Kopfdaten
 * (Kunde, Veranstaltungsort, Zeitraum) strukturell ähnlich sind. Kann
 * eigenständig, aus einem Angebot (CreateInvoiceFromOfferHandler) oder aus
 * einer Veranstaltung (CreateInvoiceFromRentalHandler) entstehen (siehe
 * $sourceOfferId/$sourceRentalId). Enthält nur die Kopfdaten; die Positionen
 * stehen in InvoiceItem.
 *
 * WICHTIG (siehe MarkInvoiceSentHandler): $invoiceNumber, $invoiceDate und
 * $dueDate bleiben NULL, solange die Rechnung ein Entwurf ist — die
 * gesetzlich relevante, fortlaufende Rechnungsnummer wird erst beim
 * Versenden vergeben. Ebenso werden $isSmallBusiness/$vatRatePercent erst
 * dabei von den aktuellen InvoiceSettings auf die Rechnung SELBST
 * gesnapshotet, damit eine spätere Änderung der firmenweiten
 * Steuereinstellungen eine bereits versendete Rechnung nie rückwirkend
 * verändert.
 */
final class Invoice
{
    public function __construct(
        public readonly string $id,
        public ?string $customerId,
        public ?string $eventLocation,
        public ?string $venueId,
        // Zeitraum der Veranstaltung — optional, damit Rechnungen auch
        // UNABHÄNGIG von einer konkreten Veranstaltung erstellt werden
        // können (siehe Migration 102). Fehlt der Zeitraum, nimmt
        // deploymentDaysForPricing() 1 Tag an.
        public ?DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public ?string $invoiceNumber,
        public ?DateTimeImmutable $invoiceDate,
        public ?DateTimeImmutable $dueDate,
        public ?string $buyerReference, // BT-10, freies Feld (z. B. Bestellnummer des Kunden), siehe XRechnungGenerator
        public InvoiceStatus $status,
        public ?bool $isSmallBusiness, // NULL bis MarkInvoiceSent (dann Snapshot), siehe Klassen-Doc
        public ?string $vatRatePercent, // NULL bis MarkInvoiceSent (dann Snapshot), als String geführt (siehe OfferItem::$unitPricePerDay)
        public ?DateTimeImmutable $paidAt,
        public ?string $note,
        public ?string $sourceOfferId,
        public ?string $sourceRentalId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Zubehörpauschale (siehe PricingSettings): pro Rechnung abwählbar,
        // beim Anlegen (bzw. bei erneuter Aktivierung) wird der dann
        // aktuelle PricingSettings::$accessoryFlatRatePercent auf die
        // Rechnung SELBST gesnapshotet (Snapshot-Prinzip wie bei
        // $isSmallBusiness/$vatRatePercent) — $accessoryFlatRatePercent ist
        // NULL, solange enabled=false.
        public bool $accessoryFlatRateEnabled = true,
        public ?string $accessoryFlatRatePercent = null,
        // Pauschalpreis für die Mietpositionen: ist $flatRateEnabled aktiv,
        // erscheinen die Mietpositionen (InvoiceItem::$isRentalItem = true)
        // auf der Rechnung ohne Einzelpreise, stattdessen zählt
        // $flatRateAmount als Zwischensumme der Mietpreise (siehe
        // App\Domain\Pricing\DocumentTotals — $flatRateAmount wird dort als
        // rentalNetTotal übergeben statt der tatsächlichen Summe der
        // Mietpositionen). Verkaufs-/Sonstige Positionen
        // (InvoiceItem::$isRentalItem = false) sind davon unberührt und
        // werden weiterhin regulär einzeln bepreist und als
        // DocumentTotals::$saleTotal aufsummiert.
        public bool $flatRateEnabled = false,
        public ?string $flatRateAmount = null,
        // Optionaler Kombirabatt: entweder 'percent' oder 'fixed', siehe
        // App\Domain\Pricing\DocumentTotals für die Berechnungsreihenfolge.
        public ?string $discountType = null,
        public ?string $discountValue = null,
        // Optionale Versandkosten (z. B. beim Verkauf einzelner Artikel,
        // siehe SellInventoryNumberHandler): einmal beim Anlegen gesetzt,
        // fasst UpdateInvoiceHandler dieses Feld nicht an, bleibt also bei
        // späteren Bearbeitungen unverändert erhalten.
        public ?string $shippingAmount = null,
        // Freitext ("Schlusstext"), erscheint in der Detailansicht und im
        // PDF-Export jeweils NACH der Positionsliste/Summenzeile — anders
        // als $note (siehe oben), das im Kopfbereich VOR der Positionsliste
        // steht. Bereits als bereinigtes Rich-Text-HTML gespeichert (siehe
        // App\Infrastructure\RichText\HtmlSanitizer, identisches Format wie
        // InvoicePdfSettings::$closingHtml, hier aber pro Rechnung statt
        // global).
        public ?string $closingText = null,
        // Logistik bei Vermietungen: unterscheidet Selbstabholung durch den
        // Kunden ('pickup', Standard/Default null wird als 'pickup'
        // behandelt) von Lieferung durch uns ('delivery'). Bei 'pickup'
        // sind $logisticsDate1/$logisticsDate2 Abhol-/Rückgabetermin DES
        // KUNDEN, bei 'delivery' Liefer-/Abholtermin DURCH UNS (siehe
        // invoices/form.php bzw. -/show.php für die je nach Methode
        // unterschiedliche Beschriftung). Rein optional, unabhängig davon,
        // ob die Rechnung über $sourceRentalId mit einer Veranstaltung
        // verknüpft ist — beim Erzeugen/Auswählen einer Veranstaltung werden
        // die Felder lediglich aus deren Rental::$pickupAt/$returnAt
        // vorbelegt (siehe CreateInvoiceFromRentalHandler/
        // RentalController::logistics()), bleiben danach aber frei
        // editierbar und unabhängig von der Veranstaltung.
        public ?string $logisticsMethod = null, // 'pickup'|'delivery'|null
        public ?DateTimeImmutable $logisticsDate1 = null,
        public ?DateTimeImmutable $logisticsDate2 = null,
        // Optionale Auswahl EINES Ansprechpartners des Kunden (Migration
        // 104): hat dieser Ansprechpartner eine eigene Adresse
        // (CustomerContact::hasOwnAddress()), wird sie statt der
        // Kundenadresse im Dokument/PDF angezeigt — siehe
        // InvoicePdfGenerator. Rein optional und unabhängig von $customerId;
        // bei Kundenwechsel im Formular wird die Auswahl im Frontend
        // zurückgesetzt, serverseitig verwirft ValidatesInvoiceInput eine
        // nicht mehr zum Kunden passende Auswahl stillschweigend.
        public ?string $contactId = null,
    ) {
    }

    /**
     * Maximal mögliche Einsatztage für den gewählten Zeitraum (inklusive
     * Start- und Endtag), analog Offer::maxDeploymentDays(). Liefert NULL,
     * wenn kein Zeitraum hinterlegt ist (siehe Migration 102).
     */
    public function maxDeploymentDays(): ?int
    {
        if ($this->startDate === null || $this->endDate === null) {
            return null;
        }

        return $this->startDate->diff($this->endDate)->days + 1;
    }

    /** Anzahl Einsatztage für die Preisstaffelung (RentalPriceCalculator kennt nur Stufen 1 bis 7). */
    public function deploymentDaysForPricing(): int
    {
        return min(max($this->maxDeploymentDays() ?? 1, 1), 7);
    }

    public function isEditable(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * True, sobald eine gesetzlich verbindliche Rechnungsnummer vergeben
     * wurde (also ab dem Versenden). Eine Rechnung, die noch als Entwurf
     * storniert wurde, hat NIE eine Nummer erhalten und liefert hier bewusst
     * false — es wurde nie etwas "finalisiert", das rückgängig zu machen wäre.
     */
    public function isFinalized(): bool
    {
        return $this->invoiceNumber !== null;
    }
}
