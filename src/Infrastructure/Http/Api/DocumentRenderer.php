<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Application\Dunning\Query\DunningNoticeDetail;
use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Application\Offer\Query\OfferDetail;
use App\Application\OrderConfirmation\Query\OrderConfirmationDetail;
use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetCompanySettings;
use App\Application\Settings\Query\GetInvoicePdfSettings;
use App\Application\Settings\Query\GetInvoiceSettings;
use App\Application\Settings\Query\GetLegalSettings;
use App\Application\Settings\Query\GetOfferPdfSettings;
use App\Application\Settings\Query\GetPdfDesignSettings;
use App\Application\Settings\Query\GetPdfLayoutSettings;
use App\Domain\Exception\ConflictException;
use App\Domain\Settings\PdfLayout\PdfDocumentType;
use App\Infrastructure\EInvoice\XRechnungGenerator;
use App\Infrastructure\InvoiceCorrection\InvoiceCorrectionDocuments;
use App\Infrastructure\Pdf\DunningPdfGenerator;
use App\Infrastructure\Pdf\InvoicePdfGenerator;
use App\Infrastructure\Pdf\OfferPdfGenerator;
use App\Infrastructure\Pdf\OrderConfirmationPdfGenerator;

/**
 * Renders the business documents for the API exactly like the web UI
 * controllers do (same generators, same settings queries, same file names).
 * Each method returns [file name, content].
 */
final class DocumentRenderer
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly OfferPdfGenerator $offerPdf,
        private readonly OrderConfirmationPdfGenerator $orderConfirmationPdf,
        private readonly InvoicePdfGenerator $invoicePdf,
        private readonly XRechnungGenerator $xRechnung,
        private readonly InvoiceCorrectionDocuments $correctionDocuments,
        private readonly DunningPdfGenerator $dunningPdf,
    ) {
    }

    /** @return array{string, string} */
    public function offerPdf(OfferDetail $detail): array
    {
        $pdf = $this->offerPdf->generate(
            $detail,
            $this->queries->ask(new GetCompanySettings()),
            $this->queries->ask(new GetLegalSettings()),
            $this->queries->ask(new GetOfferPdfSettings()),
            $this->queries->ask(new GetPdfDesignSettings()),
            $this->queries->ask(new GetPdfLayoutSettings(PdfDocumentType::Offer)),
        );

        return ['Angebot-' . strtoupper(substr($detail->offer->id, 0, 8)) . '.pdf', $pdf];
    }

    /** @return array{string, string} */
    public function orderConfirmationPdf(OrderConfirmationDetail $detail): array
    {
        $pdf = $this->orderConfirmationPdf->generate(
            $detail,
            $this->queries->ask(new GetCompanySettings()),
            $this->queries->ask(new GetPdfDesignSettings()),
            $this->queries->ask(new GetPdfLayoutSettings(PdfDocumentType::OrderConfirmation)),
        );

        return ['Auftragsbestaetigung-' . $detail->orderConfirmation->orderConfirmationNumber . '.pdf', $pdf];
    }

    /** @return array{string, string} */
    public function invoicePdf(InvoiceDetail $detail): array
    {
        $pdf = $this->invoicePdf->generate(
            $detail,
            $this->queries->ask(new GetCompanySettings()),
            $this->queries->ask(new GetInvoiceSettings()),
            $this->queries->ask(new GetLegalSettings()),
            $this->queries->ask(new GetInvoicePdfSettings()),
            $this->queries->ask(new GetPdfDesignSettings()),
            $this->queries->ask(new GetPdfLayoutSettings(PdfDocumentType::Invoice)),
        );

        return ['Rechnung-' . ($detail->invoice->invoiceNumber ?? ('Entwurf-' . strtoupper(substr($detail->invoice->id, 0, 8)))) . '.pdf', $pdf];
    }

    /** XRechnung XML; only for finalized invoices (409 otherwise). @return array{string, string} */
    public function invoiceXRechnung(InvoiceDetail $detail): array
    {
        if (!$detail->invoice->isFinalized()) {
            throw new ConflictException('Eine E-Rechnung (XRechnung) gibt es erst für festgeschriebene Rechnungen.');
        }
        $xml = $this->xRechnung->generate($detail, $this->queries->ask(new GetCompanySettings()), $this->queries->ask(new GetInvoiceSettings()));

        return ['Rechnung-' . $detail->invoice->invoiceNumber . '.xml', $xml];
    }

    /** @return array{string, string} */
    public function correctionPdf(InvoiceCorrectionDetail $detail): array
    {
        return $this->correctionDocuments->pdf($detail);
    }

    /** @return array{string, string} */
    public function correctionXRechnung(InvoiceCorrectionDetail $detail): array
    {
        return $this->correctionDocuments->xml($detail);
    }

    /** @return array{string, string} */
    public function dunningPdf(DunningNoticeDetail $detail): array
    {
        $pdf = $this->dunningPdf->generate(
            $detail,
            $this->queries->ask(new GetCompanySettings()),
            $this->queries->ask(new GetPdfLayoutSettings(PdfDocumentType::Dunning)),
        );

        return ['Mahnung-' . $detail->notice->dunningNumber . '.pdf', $pdf];
    }
}
