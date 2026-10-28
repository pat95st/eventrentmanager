<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Zentrale, wiederverwendbare Berechnung der Dokumentensummen für Angebote
 * und Rechnungen — reine PHP-Funktion ohne Seiteneffekte. Ersetzt die
 * vormals dreifach duplizierte Ad-hoc-Berechnung in InvoiceDetail/
 * OfferDetail (Anzeige) sowie InvoicePdfGenerator/OfferPdfGenerator (PDF).
 *
 * Berechnungsreihenfolge (siehe Aufgabenstellung "Zubehörpauschale" /
 * "Kombirabatt" / "Arbeitszeit" / "Zubehörpauschale auch auf
 * Freitext-Positionen"):
 *   1. rentalNetTotal      = Summe aller Mietpreis-Positionen (Zwischensumme Mietpreise).
 *   1b. freeTextTotal      = Summe aller Freitext-Posten (orderedRows()-Konzept,
 *      siehe OfferFreeTextItem/InvoiceFreeTextItem). Nutzerwunsch: anders
 *      als Arbeitszeit-/Verkaufspositionen fließen Freitext-Posten MIT in
 *      die Basis für Zubehörpauschale UND Kombirabatt ein (zusammen mit
 *      rentalNetTotal als "accessoryBase", siehe Schritt 2) — werden aber
 *      weiterhin als eigene Zwischensumme ausgewiesen (siehe
 *      invoices/show.php etc., VOR der Zubehörpauschale-Zeile).
 *   2. accessoryBase       = rentalNetTotal + freeTextTotal.
 *      accessoryFlatRateAmount = ggf. round(accessoryBase * accessoryFlatRatePercent / 100, 2).
 *      subtotalAfterAccessory = accessoryBase + accessoryFlatRateAmount.
 *   3. discountAmount      = ggf. Prozent von subtotalAfterAccessory oder Festbetrag,
 *      auf maximal subtotalAfterAccessory gekappt, nie negativ.
 *      subtotalAfterDiscount = subtotalAfterAccessory - discountAmount.
 *   4. laborTimeTotal      = Summe aller Arbeitszeit-Positionen (immer als letzte
 *      Position vor der Steuer, siehe offers/show.php, invoices/show.php,
 *      OfferPdfGenerator/InvoicePdfGenerator) — bewusst NICHT Teil von
 *      accessoryBase, da Arbeitszeit kein "Material" ist, auf das sich
 *      eine Zubehörpauschale sinnvoll bezieht.
 *   4a. saleTotal          = Summe reiner Verkaufspositionen (Einmalpreise,
 *      siehe App\Domain\Offer\OfferSaleItem) — ebenfalls bewusst NICHT
 *      Teil von accessoryBase.
 *   5. shippingAmount      = optionale, händisch angegebene Versandkosten
 *      (nur bei Rechnungen aus dem Verkauf-Workflow, siehe
 *      SellInventoryNumberHandler/Invoice::$shippingAmount); bei Angeboten
 *      immer 0.0.
 *      netTotal            = subtotalAfterDiscount + laborTimeTotal + saleTotal + shippingAmount.
 *      (freeTextTotal steckt bereits in subtotalAfterDiscount, siehe Schritt 1b/2 —
 *      NICHT nochmal addieren.)
 *   6. taxAmount/grossTotal = wie bisher aus netTotal (Kleinunternehmer: keine Steuer).
 *
 * Bei Angeboten gibt es (anders als bei Rechnungen) KEIN eigenes
 * Kleinunternehmer-/Steuersatz-Feld — ein Angebot ist ein unverbindlicher
 * Kostenvoranschlag ohne Steuerausweis (siehe Offer-Klassen-Doc/
 * offers/show.php, bereits vor diesem Feature so). OfferDetail::totals()
 * ruft diese Methode daher bewusst mit isSmallBusiness=true auf, wodurch
 * taxAmount automatisch 0 bleibt und grossTotal === netTotal — das
 * bestehende Verhalten ("Gesamtsumme (netto)") bleibt dadurch unverändert
 * erhalten, nur um die neuen Zwischensummen ergänzt.
 */
final class DocumentTotals
{
    public function __construct(
        public readonly float $rentalNetTotal,
        public readonly float $accessoryFlatRateAmount,
        public readonly float $subtotalAfterAccessory,
        public readonly float $discountAmount,
        public readonly float $subtotalAfterDiscount,
        public readonly float $laborTimeTotal,
        public readonly float $saleTotal,
        public readonly float $freeTextTotal,
        public readonly float $shippingAmount,
        public readonly float $netTotal,
        public readonly float $taxAmount,
        public readonly float $grossTotal,
    ) {
    }

    public static function calculate(
        float $rentalNetTotal,
        bool $accessoryFlatRateEnabled,
        ?string $accessoryFlatRatePercent,
        ?string $discountType,
        ?string $discountValue,
        float $laborTimeTotal,
        bool $isSmallBusiness,
        string $vatRatePercent,
        float $shippingAmount = 0.0,
        // Summe reiner Verkaufspositionen (siehe App\Domain\Offer\OfferSaleItem):
        // wie laborTimeTotal eine feste Zusatzsumme NACH dem Kombirabatt, da
        // Verkaufsposten (Einmalpreise) nicht Teil der Mietpreis-Zwischensumme
        // sind und daher auch nicht von der Zubehörpauschale/dem auf
        // Mietpreise bezogenen Rabatt betroffen sein sollen.
        float $saleTotal = 0.0,
        // Summe aller Freitext-Posten (siehe Klassendoc Punkt 4b) — wie
        // saleTotal eine optionale, standardmäßig leere Zusatzsumme.
        float $freeTextTotal = 0.0,
    ): self {
        // Nutzerwunsch (Zubehörpauschale auch auf Freitext-Positionen):
        // Freitext-Posten bilden zusammen mit den Mietpreis-Positionen die
        // Basis für Zubehörpauschale UND Kombirabatt (siehe Klassendoc
        // Schritt 1b/2) — Arbeitszeit/Verkauf bleiben davon unberührt.
        $accessoryBase = $rentalNetTotal + $freeTextTotal;

        $accessoryFlatRateAmount = 0.0;
        if ($accessoryFlatRateEnabled && $accessoryFlatRatePercent !== null) {
            $accessoryFlatRateAmount = round($accessoryBase * ((float) $accessoryFlatRatePercent) / 100, 2);
        }
        $subtotalAfterAccessory = $accessoryBase + $accessoryFlatRateAmount;

        $discountAmount = 0.0;
        if ($discountType !== null && $discountValue !== null) {
            if ($discountType === 'percent') {
                $discountAmount = round($subtotalAfterAccessory * ((float) $discountValue) / 100, 2);
            } elseif ($discountType === 'fixed') {
                $discountAmount = (float) $discountValue;
            }
            // Gekappt auf maximal das Zwischenergebnis aus Schritt 2, nie negativ.
            $discountAmount = max(0.0, min($discountAmount, $subtotalAfterAccessory));
        }
        $subtotalAfterDiscount = $subtotalAfterAccessory - $discountAmount;

        // freeTextTotal steckt bereits in subtotalAfterDiscount (siehe
        // accessoryBase oben) — hier NICHT nochmal addieren.
        $netTotal = $subtotalAfterDiscount + $laborTimeTotal + $saleTotal + $shippingAmount;

        $taxAmount = $isSmallBusiness ? 0.0 : round($netTotal * ((float) $vatRatePercent) / 100, 2);
        $grossTotal = $netTotal + $taxAmount;

        return new self(
            rentalNetTotal: $rentalNetTotal,
            accessoryFlatRateAmount: $accessoryFlatRateAmount,
            subtotalAfterAccessory: $subtotalAfterAccessory,
            discountAmount: $discountAmount,
            subtotalAfterDiscount: $subtotalAfterDiscount,
            laborTimeTotal: $laborTimeTotal,
            saleTotal: $saleTotal,
            freeTextTotal: $freeTextTotal,
            shippingAmount: $shippingAmount,
            netTotal: $netTotal,
            taxAmount: $taxAmount,
            grossTotal: $grossTotal,
        );
    }
}
