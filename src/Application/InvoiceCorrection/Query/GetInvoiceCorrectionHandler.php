<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Application\InvoiceCorrection\InvoiceCorrectionLineSource;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/** Beantwortet GetInvoiceCorrection und GetNewInvoiceCorrection. */
final class GetInvoiceCorrectionHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
        private readonly InvoiceCorrectionLineSource $lineSource,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
    ) {
    }

    public function handle(QueryInterface $query): InvoiceCorrectionDetail
    {
        if ($query instanceof GetNewInvoiceCorrection) {
            return $this->build(null, $query->invoiceId);
        }
        assert($query instanceof GetInvoiceCorrection);
        $correction = $this->corrections->findById($query->id);
        if ($correction === null) {
            throw new NotFoundException('Die Rechnungskorrektur wurde nicht gefunden.');
        }

        return $this->build($correction, $correction->invoiceId);
    }

    private function build(?\App\Domain\InvoiceCorrection\InvoiceCorrection $correction, string $invoiceId): InvoiceCorrectionDetail
    {
        $invoice = $this->lineSource->detail($invoiceId); // wirft NotFoundException
        $totals = $invoice->totals($this->invoiceSettings->get());

        $issued = [];
        foreach ($this->corrections->findByInvoice($invoiceId) as $other) {
            if ($other->status !== InvoiceCorrectionStatus::Issued || $other->id === $correction?->id) {
                continue;
            }
            // Bei einer ausgestellten Korrektur nur die vorher ausgestellten berücksichtigen.
            if ($correction !== null && !$correction->isDraft() && $other->correctionNumber !== null && $correction->correctionNumber !== null
                && strnatcmp($other->correctionNumber, $correction->correctionNumber) > 0) {
                continue;
            }
            $issued[] = $other;
        }

        return new InvoiceCorrectionDetail(
            correction: $correction,
            invoice: $invoice,
            invoiceNetTotal: round($totals->netTotal, 2),
            invoiceGrossTotal: round($totals->grossTotal, 2),
            invoiceLines: $this->lineSource->forInvoice($invoice),
            issuedCorrections: array_reverse($issued),
        );
    }
}
