<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Dunning\Query\DunningNoticeDetail;
use App\Domain\Customer\Customer;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;

/**
 * Erzeugt eine Mahnung als PDF — bewusst DEUTLICH einfacher als
 * OfferPdfGenerator/InvoicePdfGenerator (kein Fensterkuvert-Layout, kein
 * Logo, keine Mehrseiten-/Fortsetzungslogik): eine Mahnung hat immer nur
 * wenige, feststehende Zeilen (Bezug auf die Rechnung, offener Betrag,
 * ggf. Mahngebühr, Gesamtsumme) und passt daher garantiert auf eine Seite.
 * Struktur/Low-Level-Schreibmechanik (SimplePdfWriter) bewusst identisch zu
 * den anderen PDF-Generatoren dupliziert, siehe deren Klassendocs für die
 * Begründung dieses Musters.
 */
final class DunningPdfGenerator
{
    private const MARGIN = 50.0;

    public function generate(DunningNoticeDetail $detail, CompanySettings $companySettings, ?PdfLayoutSettings $layout = null): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $writer->addPage();
        $y = $pageHeight - self::MARGIN;

        $returnLine = $this->buildReturnAddressLine($companySettings);
        $returnOverride = $layout?->get('return_address');
        $returnX = self::MARGIN;
        $returnFontSize = 7.5;
        if ($returnOverride !== null) {
            $returnPoint = $returnOverride->toPdfPoint($pageHeight);
            $returnX = $returnPoint['x'];
            $y = $returnPoint['y'];
            $returnFontSize = $returnOverride->fontSize ?? 7.5;
        }
        if ($returnLine !== null) {
            $writer->text($returnX, $y, $returnLine, $returnFontSize);
            $y -= 24;
        }

        $addressOverride = $layout?->get('customer_address');
        $addressX = self::MARGIN;
        $addressFontSize = 10.0;
        if ($addressOverride !== null) {
            $addressPoint = $addressOverride->toPdfPoint($pageHeight);
            $addressX = $addressPoint['x'];
            $y = $addressPoint['y'];
            $addressFontSize = $addressOverride->fontSize ?? 10.0;
        }

        foreach ($this->buildCustomerAddressLines($detail->customer) as $line) {
            $writer->text($addressX, $y, $line, $addressFontSize);
            $y -= 13.0;
        }
        $y -= 24;

        $notice = $detail->notice;
        $invoice = $detail->invoice;

        $titleOverride = $layout?->get('title');
        $titleX = self::MARGIN;
        if ($titleOverride !== null) {
            $titlePoint = $titleOverride->toPdfPoint($pageHeight);
            $titleX = $titlePoint['x'];
            $y = $titlePoint['y'];
        }
        $titleFontSize = $titleOverride?->fontSize ?? 16.0;
        $writer->text($titleX, $y, 'Mahnung: ' . $notice->levelName, $titleFontSize, true);
        $y -= 28;

        $infoLines = [
            'Mahnungsnummer: ' . $notice->dunningNumber,
            'Datum: ' . $notice->createdAt->format('d.m.Y'),
            'Rechnungsnummer: ' . ($invoice->invoiceNumber ?? '—'),
            'Rechnungsdatum: ' . ($invoice->invoiceDate?->format('d.m.Y') ?? '—'),
            'Fällig war am: ' . ($invoice->dueDate?->format('d.m.Y') ?? '—'),
        ];
        foreach ($infoLines as $line) {
            $writer->text(self::MARGIN, $y, $line, 10);
            $y -= 14;
        }
        $y -= 12;

        $bodyText = 'Trotz Fälligkeit haben wir für die oben genannte Rechnung noch keinen Zahlungseingang feststellen können. '
            . 'Wir bitten Sie, den nachfolgend aufgeführten offenen Betrag unverzüglich zu begleichen.';
        foreach ($this->wrapText($bodyText, $pageWidth - 2 * self::MARGIN, 10.0) as $line) {
            $writer->text(self::MARGIN, $y, $line, 10.0);
            $y -= 13.0;
        }
        $y -= 16;

        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, '0.4 0.4 0.4');
        $y -= 20;

        $writer->text($pageWidth - self::MARGIN - 230, $y, 'Offener Rechnungsbetrag:', 10);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format((float) $notice->invoiceGrossTotal, 2, ',', '.') . ' EUR', 10);
        $y -= 16;

        if ($notice->feeAmount !== '0.00' && (float) $notice->feeAmount > 0.0) {
            $feeLabel = 'Mahngebühr:';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $feeLabel, 10);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format((float) $notice->feeAmount, 2, ',', '.') . ' EUR', 10);
            $y -= 16;
        }

        $writer->text($pageWidth - self::MARGIN - 230, $y, 'Gesamtbetrag fällig:', 11, true);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($notice->totalDue(), 2, ',', '.') . ' EUR', 11, true);
        $y -= 30;

        $writer->text(self::MARGIN, $y, 'Sollten Sie den Betrag bereits beglichen haben, betrachten Sie dieses Schreiben als gegenstandslos.', 8.5);

        return $writer->output();
    }

    private function buildReturnAddressLine(CompanySettings $companySettings): ?string
    {
        $parts = [];
        if ($companySettings->name !== null && trim($companySettings->name) !== '') {
            $parts[] = trim($companySettings->name);
        }
        if ($companySettings->street !== null && trim($companySettings->street) !== '') {
            $parts[] = trim($companySettings->street);
        }
        $cityLine = trim(($companySettings->postalCode ?? '') . ' ' . ($companySettings->city ?? ''));
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /** @return string[] */
    private function buildCustomerAddressLines(?Customer $customer): array
    {
        if ($customer === null) {
            return ['Kunde nicht zugeordnet'];
        }

        $lines = [$customer->displayName()];

        if ($customer->addressLine2 !== null && trim($customer->addressLine2) !== '') {
            $lines[] = trim($customer->addressLine2);
        }
        if (trim($customer->street) !== '') {
            $lines[] = trim($customer->street);
        }
        $cityLine = trim(($customer->postalCode ?? '') . ' ' . ($customer->city ?? ''));
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }

        return $lines;
    }

    /** @return string[] */
    private function wrapText(string $text, float $maxWidth, float $fontSize): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }

        $avgCharWidth = $fontSize * 0.52;
        $maxChars = max(6, (int) floor($maxWidth / $avgCharWidth));

        return explode("\n", wordwrap($text, $maxChars, "\n", true));
    }
}
