<?php

declare(strict_types=1);

namespace App\Application\Datev;

use App\Domain\Customer\Customer;
use App\Domain\Datev\DatevAccountRole;
use App\Domain\Datev\DatevBooking;
use App\Domain\Datev\DatevSettings;
use App\Domain\Invoice\Invoice;
use App\Domain\InvoiceCorrection\InvoiceCorrection;
use App\Domain\Receipt\Receipt;
use App\Domain\Supplier\Supplier;

/**
 * Übersetzt Rechnungen, Rechnungskorrekturen und Eingangsbelege in
 * DATEV-Buchungssätze — reine Logik ohne Datenbank, damit sie isoliert
 * testbar ist (siehe DatevExportBuilder für das Einsammeln der Daten).
 *
 * Buchungslogik (Soll/Haben bezieht sich auf "Konto"):
 *  - Ausgangsrechnung: Debitor (Soll) an Erlöskonto, Bruttobetrag. Die
 *    Erlöskonten 19 %/7 % sind Automatikkonten, DATEV errechnet die USt.
 *  - Rechnungskorrektur/Stornorechnung: wie die Rechnung, bei negativem
 *    Betrag mit umgekehrtem Kennzeichen (Debitor im Haben).
 *  - Eingangsbeleg: Kreditor (Haben) an Aufwandskonto je Belegkategorie,
 *    Bruttobetrag, optional mit BU-Schlüssel für die Vorsteuer.
 */
final class DatevBookingFactory
{
    /** @var string[] */
    private array $warnings = [];

    public function __construct(private readonly DatevSettings $settings)
    {
    }

    /** @return string[] Hinweise, die beim Erzeugen aufgefallen sind (z. B. Ausweichen auf Sammelkonto) */
    public function warnings(): array
    {
        return array_values(array_unique($this->warnings));
    }

    public function forInvoice(Invoice $invoice, ?Customer $customer, float $grossTotal, bool $isSmallBusiness, string $vatRatePercent): ?DatevBooking
    {
        $number = (string) $invoice->invoiceNumber;
        if (abs($grossTotal) < 0.005 || $invoice->invoiceDate === null) {
            return null;
        }

        return DatevBooking::signed(
            signedAmount: $grossTotal,
            account: $this->debtorAccount($customer, 'Rechnung ' . $number),
            contraAccount: $this->revenueAccount($isSmallBusiness, $vatRatePercent, 'Rechnung ' . $number),
            taxKey: null,
            documentDate: $invoice->invoiceDate,
            documentField1: $number,
            documentField2: null,
            text: 'Rechnung ' . $number . ($customer !== null ? ' ' . $customer->displayName() : ''),
            serviceDate: $invoice->endDate ?? $invoice->startDate,
            documentInfo: $this->customerInfo($customer),
        );
    }

    public function forCorrection(InvoiceCorrection $correction, ?Invoice $invoice, ?Customer $customer): ?DatevBooking
    {
        $number = (string) $correction->correctionNumber;
        $gross = $correction->grossTotal();
        if (abs($gross) < 0.005 || $correction->correctionDate === null) {
            return null;
        }

        $documentInfo = $this->customerInfo($customer);
        if ($invoice?->invoiceNumber !== null) {
            $documentInfo['Zu Rechnung'] = $invoice->invoiceNumber;
        }

        return DatevBooking::signed(
            signedAmount: $gross,
            account: $this->debtorAccount($customer, $correction->type->label() . ' ' . $number),
            contraAccount: $this->revenueAccount($correction->isSmallBusiness, $correction->vatRatePercent, $correction->type->label() . ' ' . $number),
            taxKey: null,
            documentDate: $correction->correctionDate,
            documentField1: $number,
            documentField2: null,
            text: $correction->type->label() . ' ' . $number . ($invoice?->invoiceNumber !== null ? ' zu ' . $invoice->invoiceNumber : ''),
            serviceDate: $invoice?->endDate ?? $invoice?->startDate,
            documentInfo: $documentInfo,
        );
    }

    public function forReceipt(Receipt $receipt, ?Supplier $supplier): ?DatevBooking
    {
        $amount = (float) $receipt->amount;
        if (abs($amount) < 0.005) {
            return null;
        }

        $documentNumber = $receipt->receiptNumber !== null && trim($receipt->receiptNumber) !== ''
            ? $receipt->receiptNumber
            : 'BELEG-' . $receipt->receiptDate->format('Ymd');

        $documentInfo = ['Kategorie' => $receipt->category->label()];
        if ($supplier !== null) {
            $documentInfo = ['Lieferant' => $supplier->name] + $documentInfo;
        }

        $taxKey = $this->settings->receiptTaxKey !== null && trim($this->settings->receiptTaxKey) !== '' ? trim($this->settings->receiptTaxKey) : null;

        // Kreditor im Haben (= negativer Betrag aus Sicht des Kreditorenkontos).
        return DatevBooking::signed(
            signedAmount: -$amount,
            account: $this->settings->effectiveCreditorAccount(),
            contraAccount: $this->settings->account(DatevAccountRole::forReceiptCategory($receipt->category)),
            taxKey: $taxKey,
            documentDate: $receipt->receiptDate,
            documentField1: $documentNumber,
            documentField2: null,
            text: trim(($supplier?->name ?? 'Beleg') . ' ' . ($receipt->receiptNumber ?? '') . ' ' . $receipt->category->label()),
            documentInfo: $documentInfo,
        );
    }

    private function debtorAccount(?Customer $customer, string $documentLabel): int
    {
        $account = $this->settings->debtorAccountFor($customer?->customerNumber);
        if ($account !== null) {
            return $account;
        }

        $this->warnings[] = $customer === null
            ? "{$documentLabel}: kein Kunde hinterlegt — auf das Debitoren-Sammelkonto {$this->settings->effectiveDebtorAccount()} gebucht."
            : "{$documentLabel}: aus der Kundennummer \"{$customer->customerNumber}\" ließ sich kein gültiges Debitorenkonto ableiten — auf das Sammelkonto {$this->settings->effectiveDebtorAccount()} gebucht.";

        return $this->settings->effectiveDebtorAccount();
    }

    private function revenueAccount(bool $isSmallBusiness, string $vatRatePercent, string $documentLabel): int
    {
        if ($isSmallBusiness) {
            return $this->settings->account(DatevAccountRole::RevenueSmallBusiness);
        }

        $rate = round((float) $vatRatePercent, 2);

        return match (true) {
            abs($rate - 19.0) < 0.001 => $this->settings->account(DatevAccountRole::Revenue19),
            abs($rate - 7.0) < 0.001 => $this->settings->account(DatevAccountRole::Revenue7),
            abs($rate) < 0.001 => $this->settings->account(DatevAccountRole::RevenueTaxFree),
            default => $this->unsupportedRate($rate, $documentLabel),
        };
    }

    private function unsupportedRate(float $rate, string $documentLabel): int
    {
        $account = $this->settings->account(DatevAccountRole::Revenue19);
        $this->warnings[] = "{$documentLabel}: Steuersatz " . str_replace('.', ',', (string) $rate) . " % wird nicht automatisch zugeordnet — auf Konto {$account} gebucht, bitte prüfen.";

        return $account;
    }

    /** @return array<string, string> */
    private function customerInfo(?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        return [
            'Kunde' => $customer->displayName(),
            'Kundennummer' => $customer->customerNumber,
        ];
    }
}
