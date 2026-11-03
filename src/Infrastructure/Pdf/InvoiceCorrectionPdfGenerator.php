<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\InvoiceCorrection\Query\InvoiceCorrectionDetail;
use App\Domain\Customer\Customer;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Domain\Settings\CompanySettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Stornorechnung bzw. Rechnungskorrektur als PDF (Migration 121).
 *
 * Pflichtangaben einer Rechnungsberichtigung: eigene Nummer und Datum,
 * eindeutiger Bezug auf die ursprüngliche Rechnung (Nummer und Datum), die
 * geänderten Positionen mit Netto, Umsatzsteuer und Brutto sowie der Grund.
 * Bei Teilkorrekturen zusätzlich der neue Rechnungsbetrag.
 * Entwürfe tragen deutlich sichtbar „ENTWURF“.
 */
final class InvoiceCorrectionPdfGenerator
{
    private const MARGIN = 50.0;
    private const BOTTOM_LIMIT = 140.0; // Platz für die Fußzeile (DocumentFooter)
    private const FONT_SIZE = 9.0;
    private const LINE_HEIGHT = 12.0;
    private const LOGO_MAX_WIDTH = 140.0;
    private const LOGO_MAX_HEIGHT = 60.0;

    public function __construct(
        private readonly LocalFileStorage $fileStorage,
        private readonly ?DocumentFooter $footer = null,
        private readonly ?PdfAmountFormatter $amountFormatter = null,
    ) {
    }

    public function generate(InvoiceCorrectionDetail $detail, CompanySettings $companySettings): string
    {
        $correction = $detail->correction;
        assert($correction !== null);
        $invoice = $detail->invoice->invoice;

        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();
        $contentWidth = $pageWidth - 2 * self::MARGIN;
        $title = $correction->type->label();
        $pageNumber = 1;

        $writer->addPage();
        $logo = $this->prepareLogoJpeg($companySettings);
        if ($logo !== null) {
            [$w, $h] = $this->fit($logo, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT);
            $writer->image($logo, $pageWidth - self::MARGIN - $w, $pageHeight - 40 - $h, $w, $h);
        }
        if ($correction->isDraft()) {
            $writer->text(self::MARGIN, $pageHeight - 60, 'ENTWURF – noch nicht ausgestellt', 14, true, false, '0.8 0.15 0.15');
        }

        // Absender und Empfänger (Fensterkuvert DIN 5008)
        $y = $pageHeight - 140;
        $returnLine = $this->returnAddressLine($companySettings);
        if ($returnLine !== null) {
            $writer->text(self::MARGIN, $y, $returnLine, 7.5);
            $writer->line(self::MARGIN, $y - 2, self::MARGIN + $writer->textWidth($returnLine, 7.5), $y - 2, 0.4, '0.3 0.3 0.3');
        }
        $y -= 18;
        foreach ($this->customerAddressLines($detail->invoice->customer) as $line) {
            $writer->text(self::MARGIN, $y, $line, 10);
            $y -= 13;
        }

        // Rechte Spalte: Dokumentdaten
        $infoX = $pageWidth - self::MARGIN - 200;
        $infoY = $pageHeight - 140;
        $info = [
            [$title . '-Nr.', $correction->correctionNumber ?? 'Entwurf'],
            ['Datum', $correction->correctionDate?->format('d.m.Y') ?? (new \DateTimeImmutable('today'))->format('d.m.Y')],
            ['Zur Rechnung', $invoice->invoiceNumber ?? '–'],
            ['Rechnungsdatum', $invoice->invoiceDate?->format('d.m.Y') ?? '–'],
        ];
        if ($invoice->startDate !== null) {
            $info[] = ['Leistungszeitraum', $invoice->startDate->format('d.m.Y') . ($invoice->endDate !== null && $invoice->endDate != $invoice->startDate ? ' – ' . $invoice->endDate->format('d.m.Y') : '')];
        }
        if ($detail->invoice->customer !== null && $detail->invoice->customer->customerNumber !== '') {
            $info[] = ['Kundennummer', $detail->invoice->customer->customerNumber];
        }
        foreach ($info as [$label, $value]) {
            $writer->text($infoX, $infoY, $label . ':', 8.5, false, false, '0.35 0.35 0.35');
            $writer->textRight($pageWidth - self::MARGIN, $infoY, $value, 8.5, true);
            $infoY -= 12;
        }

        $y = min($y, $infoY) - 30;
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y, $title . ($correction->correctionNumber !== null ? ' ' . $correction->correctionNumber : ''), 16, true);
        $y -= 24;

        $invoiceRef = 'Rechnung Nr. ' . ($invoice->invoiceNumber ?? '–') . ' vom ' . ($invoice->invoiceDate?->format('d.m.Y') ?? '–');
        $intro = $correction->type === InvoiceCorrectionType::Cancellation
            ? 'Hiermit stornieren wir die ' . $invoiceRef . ' vollständig.'
            : 'Hiermit berichtigen wir die ' . $invoiceRef . ' wie folgt. Aufgeführt sind nur die Änderungen gegenüber der Rechnung.';
        foreach ($this->wrap($intro, $contentWidth, 10) as $line) {
            $writer->text(self::MARGIN, $y, $line, 10);
            $y -= 13;
        }
        $y -= 4;
        foreach ($this->wrap('Grund: ' . $correction->reason, $contentWidth, 10) as $i => $line) {
            $writer->text(self::MARGIN, $y, $line, 10, $i === 0);
            $y -= 13;
        }
        $y -= 12;

        // Positionen
        $columns = $this->columns($pageWidth);
        $newPage = function () use ($writer, $pageWidth, $pageHeight, &$pageNumber, $title, $correction): float {
            $pageNumber++;
            $writer->addPage();
            $y = $pageHeight - self::MARGIN;
            $writer->text(self::MARGIN, $y, $title . ' ' . ($correction->correctionNumber ?? '(Entwurf)') . ' – Fortsetzung', 10, true);
            $writer->textRight($pageWidth - self::MARGIN, $y, 'Seite ' . $pageNumber, 8);

            return $y - 24;
        };
        $previousRole = $writer->setFontRole(SimplePdfWriter::ROLE_TABLE);
        $y = $this->tableHeader($writer, $y, $pageWidth, $columns);
        foreach ($correction->lines as $line) {
            $nameLines = $this->wrap($line->description, $columns['description']['width'], self::FONT_SIZE);
            $height = count($nameLines) * self::LINE_HEIGHT + 6;
            if ($y - $height < self::BOTTOM_LIMIT) {
                $y = $this->tableHeader($writer, $newPage(), $pageWidth, $columns);
            }
            $baseline = $y - self::LINE_HEIGHT + 2;
            foreach ($nameLines as $i => $nameLine) {
                $writer->text($columns['description']['x'], $baseline - $i * self::LINE_HEIGHT, $nameLine, self::FONT_SIZE);
            }
            $writer->textRight($this->right($columns['quantity']), $baseline, $this->quantity($line->quantity), self::FONT_SIZE);
            $writer->textRight($this->right($columns['price']), $baseline, $this->amount((float) $line->unitPrice), self::FONT_SIZE);
            $writer->textRight($this->right($columns['total']), $baseline, $this->amount($line->lineTotal()), self::FONT_SIZE);
            $y -= $height;
            $writer->line(self::MARGIN, $y + 2, $pageWidth - self::MARGIN, $y + 2, 0.3, '0.85 0.85 0.85');
        }
        $writer->setFontRole($previousRole);

        // Summen
        $summary = [['Summe netto', $correction->netTotal(), false]];
        if (!$correction->isSmallBusiness) {
            $summary[] = ['zzgl. ' . $this->quantity($correction->vatRatePercent) . ' % USt', $correction->taxAmount(), false];
        }
        $summary[] = [$correction->type === InvoiceCorrectionType::Cancellation ? 'Stornobetrag brutto' : 'Korrekturbetrag brutto', $correction->grossTotal(), true];
        $showNewTotal = $correction->type === InvoiceCorrectionType::Partial || $detail->issuedCorrections !== [];
        $needed = (count($summary) + ($showNewTotal ? 4 : 0)) * 15 + 90;
        if ($y - $needed < self::BOTTOM_LIMIT) {
            $y = $newPage();
        }
        $y -= 14;
        $labelX = $pageWidth - self::MARGIN - 250;
        foreach ($summary as [$label, $value, $bold]) {
            if ($bold) {
                $writer->line($labelX, $y + 10, $pageWidth - self::MARGIN, $y + 10, 0.6, '0.4 0.4 0.4');
            }
            $writer->text($labelX, $y, $label . ':', $bold ? 10.5 : 9.5, $bold);
            $writer->textRight($pageWidth - self::MARGIN, $y, $this->amount($value), $bold ? 10.5 : 9.5, $bold);
            $y -= 15;
        }
        if ($correction->isSmallBusiness) {
            $writer->text(self::MARGIN, $y, 'Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.', 8.5);
            $y -= 13;
        }

        if ($showNewTotal) {
            $y -= 8;
            $rows = [['Ursprünglicher Rechnungsbetrag', $detail->invoiceGrossTotal]];
            if ($detail->issuedCorrections !== []) {
                $rows[] = ['Bisherige Korrekturen', round($detail->grossBefore() - $detail->invoiceGrossTotal, 2)];
            }
            $rows[] = ['Diese ' . $title, $correction->grossTotal()];
            foreach ($rows as [$label, $value]) {
                $writer->text($labelX, $y, $label . ':', 8.5, false, false, '0.3 0.3 0.3');
                $writer->textRight($pageWidth - self::MARGIN, $y, $this->amount($value), 8.5, false, false, '0.3 0.3 0.3');
                $y -= 12;
            }
            $writer->line($labelX, $y + 9, $pageWidth - self::MARGIN, $y + 9, 0.4, '0.6 0.6 0.6');
            $writer->text($labelX, $y - 2, 'Neuer Rechnungsbetrag brutto:', 9.5, true);
            $writer->textRight($pageWidth - self::MARGIN, $y - 2, $this->amount($detail->grossAfter()), 9.5, true);
            $y -= 20;
        }

        // Zahlungshinweis
        $y -= 12;
        foreach ($this->wrap($this->paymentText($detail), $contentWidth, 9.5) as $line) {
            if ($y < self::BOTTOM_LIMIT) {
                $y = $newPage();
            }
            $writer->text(self::MARGIN, $y, $line, 9.5);
            $y -= 12.5;
        }
        if ($correction->note !== null) {
            $y -= 8;
            foreach ($this->wrap($correction->note, $contentWidth, 9.5) as $line) {
                if ($y < self::BOTTOM_LIMIT) {
                    $y = $newPage();
                }
                $writer->text(self::MARGIN, $y, $line, 9.5);
                $y -= 12.5;
            }
        }

        $this->footer?->drawOnAllPages($writer, $detail->invoice->bankAccount, $invoice->bankAccountId);

        return $writer->output();
    }

    private function paymentText(InvoiceCorrectionDetail $detail): string
    {
        $correction = $detail->correction;
        $gross = $correction->grossTotal();
        $paid = $correction->isDraft() ? $detail->invoice->invoice->status === InvoiceStatus::Paid : $correction->requiresSettlement;
        $amount = $this->amount(abs($gross));

        if (abs($gross) < 0.005) {
            return 'Durch diese Korrektur ändert sich der Rechnungsbetrag nicht.';
        }
        if ($gross < 0) {
            if ($paid) {
                return 'Den Betrag von ' . $amount . ' erstatten wir Ihnen in den nächsten Tagen auf Ihr Konto.';
            }

            return $correction->type === InvoiceCorrectionType::Cancellation
                ? 'Die Rechnung ist damit gegenstandslos. Bitte überweisen Sie den Rechnungsbetrag nicht.'
                : 'Bitte überweisen Sie nur noch den neuen Rechnungsbetrag von ' . $this->amount(max(0.0, $detail->grossAfter())) . '.';
        }

        return 'Bitte überweisen Sie den Differenzbetrag von ' . $amount . ' unter Angabe der Korrekturnummer auf das unten angegebene Konto.';
    }

    /** @return array<string, array{x: float, width: float}> */
    private function columns(float $pageWidth): array
    {
        $right = $pageWidth - self::MARGIN;

        return [
            'description' => ['x' => self::MARGIN + 4, 'width' => $right - 250 - self::MARGIN - 12],
            'quantity' => ['x' => $right - 250, 'width' => 55],
            'price' => ['x' => $right - 190, 'width' => 95],
            'total' => ['x' => $right - 95, 'width' => 95],
        ];
    }

    /** @param array{x: float, width: float} $column */
    private function right(array $column): float
    {
        return $column['x'] + $column['width'] - 4;
    }

    /** @param array<string, array{x: float, width: float}> $columns */
    private function tableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, array $columns): float
    {
        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, '0.92 0.92 0.92');
        $writer->text($columns['description']['x'], $y - 9, 'Bezeichnung', self::FONT_SIZE, true);
        $writer->textRight($this->right($columns['quantity']), $y - 9, 'Menge', self::FONT_SIZE, true);
        $writer->textRight($this->right($columns['price']), $y - 9, 'Einzelpreis', self::FONT_SIZE, true);
        $writer->textRight($this->right($columns['total']), $y - 9, 'Summe', self::FONT_SIZE, true);

        return $y - 20;
    }

    private function amount(float $value): string
    {
        $value = abs($value) < 0.005 ? 0.0 : $value;

        return number_format($value, 2, ',', '.') . ($this->amountFormatter?->suffix() ?? ' EUR');
    }

    private function quantity(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    private function returnAddressLine(CompanySettings $companySettings): ?string
    {
        $parts = array_filter([
            trim((string) $companySettings->name),
            trim((string) $companySettings->street),
            trim(($companySettings->postalCode ?? '') . ' ' . ($companySettings->city ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** @return string[] */
    private function customerAddressLines(?Customer $customer): array
    {
        if ($customer === null) {
            return ['Kunde nicht zugeordnet'];
        }
        $lines = [$customer->displayName()];
        foreach ([$customer->addressLine2, $customer->street] as $part) {
            if ($part !== null && trim($part) !== '') {
                $lines[] = trim($part);
            }
        }
        $city = trim(($customer->postalCode ?? '') . ' ' . ($customer->city ?? ''));
        if ($city !== '') {
            $lines[] = $city;
        }

        return $lines;
    }

    /** @return string[] */
    private function wrap(string $text, float $maxWidth, float $fontSize): array
    {
        $result = [];
        $maxChars = max(6, (int) floor($maxWidth / ($fontSize * 0.5)));
        foreach (preg_split('/\R/', trim($text)) ?: [''] as $paragraph) {
            foreach (explode("\n", wordwrap($paragraph, $maxChars, "\n", true)) as $line) {
                $result[] = $line;
            }
        }

        return $result;
    }

    private function prepareLogoJpeg(CompanySettings $companySettings): ?string
    {
        if ($companySettings->logoFilename === null || !extension_loaded('gd')) {
            return null;
        }
        $path = $this->fileStorage->absolutePath('company', $companySettings->logoFilename);
        $data = is_file($path) ? (string) @file_get_contents($path) : '';
        $source = $data !== '' ? @imagecreatefromstring($data) : false;
        if ($source === false) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);
        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        return $jpeg === false || $jpeg === '' ? null : $jpeg;
    }

    /** @return array{0: float, 1: float} */
    private function fit(string $jpeg, float $maxWidth, float $maxHeight): array
    {
        $size = @getimagesizefromstring($jpeg);
        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return [$maxWidth, $maxHeight];
        }
        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return [$size[0] * $scale, $size[1] * $scale];
    }
}
