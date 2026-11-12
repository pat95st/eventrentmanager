<?php

declare(strict_types=1);

namespace App\Infrastructure\Datev;

use App\Application\Datev\DatevDocumentSourceInterface;
use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Domain\Receipt\Receipt;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\InvoicePdfSettingsRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Domain\Settings\LegalSettingsRepositoryInterface;
use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;
use App\Domain\Settings\PdfLayout\PdfDocumentType;
use App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\InvoiceCorrection\InvoiceCorrectionDocuments;
use App\Infrastructure\Pdf\InvoicePdfGenerator;

/**
 * Erzeugt die Belegdateien für das DATEV-Paket mit denselben Generatoren
 * wie Download/E-Mail-Versand (siehe EmailInvoicePdfHandler,
 * InvoiceCorrectionDocuments), damit der Steuerberater exakt die Belege
 * erhält, die auch der Kunde bekommen hat.
 */
final class DatevDocumentSource implements DatevDocumentSourceInterface
{
    public function __construct(
        private readonly InvoicePdfGenerator $invoicePdfGenerator,
        private readonly InvoiceCorrectionDocuments $correctionDocuments,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly LegalSettingsRepositoryInterface $legalSettings,
        private readonly InvoicePdfSettingsRepositoryInterface $invoicePdfSettings,
        private readonly PdfDesignSettingsRepositoryInterface $pdfDesignSettings,
        private readonly PdfLayoutSettingsRepositoryInterface $pdfLayoutSettings,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function invoicePdf(InvoiceDetail $detail): array
    {
        $pdf = $this->invoicePdfGenerator->generate(
            $detail,
            $this->companySettings->get(),
            $this->invoiceSettings->get(),
            $this->legalSettings->get(),
            $this->invoicePdfSettings->get(),
            $this->pdfDesignSettings->get(),
            $this->pdfLayoutSettings->getForDocumentType(PdfDocumentType::Invoice),
        );

        return ['Rechnung-' . $detail->invoice->invoiceNumber . '.pdf', $pdf];
    }

    public function correctionPdf(InvoiceCorrectionDetail $detail): array
    {
        return $this->correctionDocuments->pdf($detail);
    }

    public function receiptFile(Receipt $receipt): ?array
    {
        $path = $this->fileStorage->absolutePath('receipts', $receipt->storedFilename);
        if (!is_file($path)) {
            return null;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $extension = pathinfo($receipt->originalFilename, PATHINFO_EXTENSION) ?: pathinfo($receipt->storedFilename, PATHINFO_EXTENSION);
        $base = $receipt->receiptDate->format('Y-m-d') . '_' . ($receipt->receiptNumber ?? pathinfo($receipt->originalFilename, PATHINFO_FILENAME));

        return [$base . ($extension !== '' ? '.' . $extension : ''), $content];
    }
}
