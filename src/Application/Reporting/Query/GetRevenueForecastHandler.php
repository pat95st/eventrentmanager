<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\Pricing\DocumentTotals;
use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/**
 * Baut die Umsatzprognose für $yearCount Kalenderjahre ab $fromYear —
 * anders als GetIncomeExpenseOverviewHandler (Zufluss-/Abfluss-Prinzip nach
 * tatsächlichem Zahlungsdatum) geht es hier um bereits GEBUCHTES/erwartetes
 * Geschäft, eingeordnet nach dem Veranstaltungszeitraum (startDate) der
 * jeweiligen Angebote/Auftragsbestätigungen/Rechnungen:
 *
 *   1. Angebote (offen, nicht abgelehnt — Draft/Sent/Accepted, siehe
 *      OfferRepositoryInterface::findOpenForRevenueForecast()). Ein Angebot,
 *      das bereits in eine Auftragsbestätigung oder Rechnung übernommen
 *      wurde (sourceOfferId), wird NICHT zusätzlich gezählt — sonst würde
 *      dasselbe Geschäft doppelt in die Prognose einfließen.
 *   2. Auftragsbestätigungen (nicht storniert — Draft/Sent/Confirmed).
 *   3. Rechnungen (nicht storniert — Draft/Sent/Paid), unabhängig vom
 *      Zahlungsstatus (anders als die Einnahmen-/Ausgabenübersicht).
 *
 * Berechnungsgrundlage je Dokument ist App\Domain\Pricing\DocumentTotals,
 * aber bewusst der NETTO-Betrag (netTotal), nicht der Brutto-Betrag: Weder
 * Angebote noch Auftragsbestätigungen weisen überhaupt eine Steuer aus
 * (kein isSmallBusiness-/vatRatePercent-Feld, siehe deren Klassen-Doc), eine
 * gemischte Brutto-Summe wäre also nicht zwischen den drei Herkünften
 * vergleichbar. Entwurf-Rechnungen haben noch keinen gesnapshoteten
 * Steuersatz (isSmallBusiness/vatRatePercent sind dann NULL) — hier wie bei
 * GetIncomeExpenseOverviewHandler mit den aktuellen InvoiceSettings als
 * Fallback, der ohnehin für netTotal irrelevant ist (Steuer wirkt sich nur
 * auf grossTotal aus).
 *
 * Jahre außerhalb des angefragten Zeitraums (z. B. ein Angebot mit
 * Veranstaltungsdatum weit in der Vergangenheit) werden stillschweigend
 * übersprungen, nicht als Fehler behandelt.
 */
final class GetRevenueForecastHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
    ) {
    }

    public function handle(QueryInterface $query): RevenueForecast
    {
        assert($query instanceof GetRevenueForecast);

        $amountsByYear = [];
        for ($i = 0; $i < $query->yearCount; $i++) {
            $amountsByYear[$query->fromYear + $i] = ['offer' => 0.0, 'orderConfirmation' => 0.0, 'invoice' => 0.0];
        }

        $invoiceSettings = $this->invoiceSettings->get();

        // Verhindert Doppelzählung: ein Angebot, das bereits in eine
        // Auftragsbestätigung oder Rechnung übernommen wurde, zählt nur dort.
        $convertedOfferIds = array_flip(array_merge(
            $this->orderConfirmations->findSourceOfferIds(),
            $this->invoices->findSourceOfferIds(),
        ));

        foreach ($this->offers->findOpenForRevenueForecast() as $offer) {
            assert($offer instanceof Offer);
            if (isset($convertedOfferIds[$offer->id])) {
                continue;
            }

            $year = (int) $offer->startDate->format('Y');
            if (!isset($amountsByYear[$year])) {
                continue;
            }

            $amountsByYear[$year]['offer'] += $this->offerNetTotal($offer);
        }

        foreach ($this->orderConfirmations->findNotCancelledForRevenueForecast() as $orderConfirmation) {
            assert($orderConfirmation instanceof OrderConfirmation);

            $year = (int) $orderConfirmation->startDate->format('Y');
            if (!isset($amountsByYear[$year])) {
                continue;
            }

            $amountsByYear[$year]['orderConfirmation'] += $this->orderConfirmationNetTotal($orderConfirmation);
        }

        foreach ($this->invoices->findNotCancelledForRevenueForecast() as $invoice) {
            assert($invoice instanceof Invoice);

            $year = (int) $invoice->startDate->format('Y');
            if (!isset($amountsByYear[$year])) {
                continue;
            }

            $amountsByYear[$year]['invoice'] += $this->invoiceNetTotal($invoice, $invoiceSettings);
        }

        $years = [];
        foreach ($amountsByYear as $year => $amounts) {
            $years[] = new RevenueForecastYear(
                year: $year,
                offerAmount: $amounts['offer'],
                orderConfirmationAmount: $amounts['orderConfirmation'],
                invoiceAmount: $amounts['invoice'],
            );
        }

        return new RevenueForecast($years);
    }

    private function offerNetTotal(Offer $offer): float
    {
        $rentalNetTotal = $this->sumLineTotals($this->offers->findItemsByOffer($offer->id));
        $laborTimeTotal = $this->sumLineTotals($this->offers->findLaborItemsByOffer($offer->id));
        $saleTotal = $this->sumLineTotals($this->offers->findSaleItemsByOffer($offer->id));
        $freeTextTotal = $this->sumLineTotals($this->offers->findFreeTextItemsByOffer($offer->id));

        $totals = DocumentTotals::calculate(
            rentalNetTotal: $rentalNetTotal,
            accessoryFlatRateEnabled: $offer->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $offer->accessoryFlatRatePercent,
            discountType: $offer->discountType,
            discountValue: $offer->discountValue,
            laborTimeTotal: $laborTimeTotal,
            // Angebote weisen grundsätzlich keine Steuer aus (siehe
            // OfferDetail::totals()) — für netTotal ohnehin irrelevant.
            isSmallBusiness: true,
            vatRatePercent: '0',
            saleTotal: $saleTotal,
            freeTextTotal: $freeTextTotal,
        );

        return $totals->netTotal;
    }

    private function orderConfirmationNetTotal(OrderConfirmation $orderConfirmation): float
    {
        $rentalNetTotal = $this->sumLineTotals($this->orderConfirmations->findItemsByOrderConfirmation($orderConfirmation->id));
        $freeTextTotal = $this->sumLineTotals($this->orderConfirmations->findFreeTextItemsByOrderConfirmation($orderConfirmation->id));

        $totals = DocumentTotals::calculate(
            rentalNetTotal: $rentalNetTotal,
            accessoryFlatRateEnabled: $orderConfirmation->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $orderConfirmation->accessoryFlatRatePercent,
            discountType: $orderConfirmation->discountType,
            discountValue: $orderConfirmation->discountValue,
            // Auftragsbestätigungen kennen weder Arbeitszeit-/Verkaufspositionen
            // noch ein eigenes Steuerfeld (siehe Klassen-Doc).
            laborTimeTotal: 0.0,
            isSmallBusiness: true,
            vatRatePercent: '0',
            freeTextTotal: $freeTextTotal,
        );

        return $totals->netTotal;
    }

    private function invoiceNetTotal(Invoice $invoice, InvoiceSettings $invoiceSettings): float
    {
        $rentalNetTotal = $this->sumLineTotals($this->invoices->findItemsByInvoice($invoice->id));
        $laborTimeTotal = $this->sumLineTotals($this->invoices->findLaborItemsByInvoice($invoice->id));
        $freeTextTotal = $this->sumLineTotals($this->invoices->findFreeTextItemsByInvoice($invoice->id));

        $totals = DocumentTotals::calculate(
            rentalNetTotal: $rentalNetTotal,
            accessoryFlatRateEnabled: $invoice->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $invoice->accessoryFlatRatePercent,
            discountType: $invoice->discountType,
            discountValue: $invoice->discountValue,
            laborTimeTotal: $laborTimeTotal,
            isSmallBusiness: $invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness,
            vatRatePercent: $invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent,
            freeTextTotal: $freeTextTotal,
        );

        return $totals->netTotal;
    }

    /** @param array<int, object> $lines jedes Element muss eine lineTotal()-Methode besitzen */
    private function sumLineTotals(array $lines): float
    {
        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += $line->lineTotal();
        }

        return $sum;
    }
}
