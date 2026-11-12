<?php

declare(strict_types=1);

namespace App\Application\Datev;

use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Domain\Receipt\Receipt;

/** Liefert die Belegdateien (PDF/Bild) für das DATEV-Paket, siehe DatevExportBuilder. */
interface DatevDocumentSourceInterface
{
    /** @return array{0: string, 1: string} [Dateiname, Inhalt] */
    public function invoicePdf(InvoiceDetail $detail): array;

    /** @return array{0: string, 1: string} [Dateiname, Inhalt] */
    public function correctionPdf(InvoiceCorrectionDetail $detail): array;

    /** @return array{0: string, 1: string}|null [Dateiname, Inhalt]; NULL, wenn die Datei fehlt */
    public function receiptFile(Receipt $receipt): ?array;
}
