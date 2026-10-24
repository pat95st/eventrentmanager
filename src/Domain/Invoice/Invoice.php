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
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
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
        // Optionaler Kombirabatt: entweder 'percent' oder 'fixed', siehe
        // App\Domain\Pricing\DocumentTotals für die Berechnungsreihenfolge.
        public ?string $discountType = null,
        public ?string $discountValue = null,
        // Optionale Versandkosten (z. B. beim Verkauf einzelner Artikel,
        // siehe SellInventoryNumberHandler): einmal beim Anlegen gesetzt,
        // fasst UpdateInvoiceHandler dieses Feld nicht an, bleibt also bei
        // späteren Bearbeitungen unverändert erhalten.
        public ?string $shippingAmount = null,
    ) {
    }

    /** Maximal mögliche Einsatztage für den gewählten Zeitraum (inklusive Start- und Endtag), analog Offer::maxDeploymentDays(). */
    public function maxDeploymentDays(): int
    {
        return $this->startDate->diff($this->endDate)->days + 1;
    }

    /** Anzahl Einsatztage für die Preisstaffelung (RentalPriceCalculator kennt nur Stufen 1 bis 7). */
    public function deploymentDaysForPricing(): int
    {
        return min(max($this->maxDeploymentDays(), 1), 7);
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
