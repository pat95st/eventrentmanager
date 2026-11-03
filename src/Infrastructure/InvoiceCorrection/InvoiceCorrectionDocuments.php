<?php

declare(strict_types=1);

namespace App\Infrastructure\InvoiceCorrection;

use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Infrastructure\EInvoice\XRechnungGenerator;
use App\Infrastructure\Pdf\InvoiceCorrectionPdfGenerator;

/**
 * Erzeugt die Dateien einer Rechnungskorrektur an einer Stelle: PDF und
 * E-Rechnung (XRechnung-XML), mit einheitlichen Dateinamen. Genutzt von
 * Download, E-Mail-Versand und automatischer Ablage.
 */
final class InvoiceCorrectionDocuments
{
    public function __construct(
        private readonly InvoiceCorrectionPdfGenerator $pdfGenerator,
        private readonly XRechnungGenerator $xRechnungGenerator,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
    ) {
    }

    public function baseName(InvoiceCorrectionDetail $detail): string
    {
        $correction = $detail->correction;

        return ($correction->type === InvoiceCorrectionType::Cancellation ? 'Stornorechnung-' : 'Rechnungskorrektur-')
            . preg_replace('/[^A-Za-z0-9._-]+/', '-', $correction->correctionNumber ?? 'Entwurf');
    }

    /** @return array{0: string, 1: string} [Dateiname, Inhalt] */
    public function pdf(InvoiceCorrectionDetail $detail): array
    {
        return [$this->baseName($detail) . '.pdf', $this->pdfGenerator->generate($detail, $this->companySettings->get())];
    }

    /** @return array{0: string, 1: string} [Dateiname, Inhalt]; nur für ausgestellte Korrekturen */
    public function xml(InvoiceCorrectionDetail $detail): array
    {
        $xml = $this->xRechnungGenerator->generateCorrection(
            $detail,
            $this->companySettings->get(),
            $this->invoiceSettings->get(),
            $this->pdfGenerator->paymentText($detail),
        );

        return [$this->baseName($detail) . '.xml', $xml];
    }

    public function paymentText(InvoiceCorrectionDetail $detail): string
    {
        return $this->pdfGenerator->paymentText($detail);
    }
}
