<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\OrderConfirmation\Query\OrderConfirmationDetail;
use App\Application\OrderConfirmation\Query\OrderConfirmationRow;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContact;
use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Vereinfachter Klon von OfferPdfGenerator für Auftragsbestätigungen:
 * gleicher Aufbau (Logo, Fensterkuvert-Adresszone, Kopfdaten, Tabelle,
 * Summenblock), aber bewusst OHNE globale Einleitungs-/Schlusstext-
 * Rich-Text-Einstellungen (keine eigenen PDF-Settings für
 * Auftragsbestätigungen, siehe OrderConfirmation-Klassen-Doc) und ohne
 * angehängte Vermietbedingungen — nur PDF-Download, kein E-Mail-Workflow.
 * Die Tabelle rendert orderedRows() (reguläre Positionen, Freitext-Posten
 * UND Zwischenüberschriften in einer gemeinsamen Reihenfolge), nicht nur
 * reine Artikelpositionen.
 *
 * Seit dem dokumenteigenen "Schlusstext" (OrderConfirmation::$closingText,
 * Kundenwunsch — siehe Invoice-/OfferPdfGenerator für die identische
 * Begründung) bekommt diese Klasse als EINZIGE Rich-Text-Abhängigkeit
 * $richTextRenderer injiziert, rein für dieses Feld — es gibt (anders als
 * bei Rechnung/Angebot) keinen globalen Einleitungs-/Schlusstext, der
 * dieselbe Infrastruktur bereits mitgebracht hätte.
 */
final class OrderConfirmationPdfGenerator
{
    private const MARGIN = 40.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;
    private const TABLE_FONT_SIZE = 8.5;
    private const TABLE_LINE_HEIGHT = 11.0;
    private const TABLE_ROW_PADDING = 6.0;

    private const MM_TO_PT = 2.83465;
    private const FOLD_MARK_LENGTH_MM = 4.0;
    private const FOLD_MARK_AT_MM = [105.0, 210.0];
    private const WINDOW_ZONE_LEFT_MM = 20.0;
    private const WINDOW_ZONE_TOP_MM = 45.0;
    private const WINDOW_ZONE_WIDTH_MM = 85.0;
    private const WINDOW_ZONE_BOTTOM_MM = 90.0;

    public function __construct(
        private readonly LocalFileStorage $fileStorage,
        private readonly RichTextPdfRenderer $richTextRenderer,
    ) {
    }

    public function generate(OrderConfirmationDetail $detail, CompanySettings $companySettings, ?PdfDesignSettings $pdfDesignSettings = null, ?PdfLayoutSettings $layout = null): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $logoJpeg = $this->prepareLogoJpeg($companySettings);
        $logoBox = $logoJpeg !== null ? $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT) : null;

        $rows = $this->buildRows($detail);
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $detail, $companySettings, $logoJpeg, $logoBox, $pageWidth, $pageHeight, $pageNumber, continuation: false, pdfDesignSettings: $pdfDesignSettings, layout: $layout);
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);

        if ($rows === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Keine Positionen erfasst.', 10);
            $y -= self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;
        }

        foreach ($rows as $row) {
            $rowLineCount = max(count($row['nameLines']), 1);
            $rowHeight = $rowLineCount * self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 60) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $pdfDesignSettings);
            }

            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        if ($y - 70 < self::MARGIN + 60) {
            $pageNumber++;
            $writer->addPage();
            $y = $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
        }

        $totals = $detail->totals();

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        $writer->text($pageWidth - self::MARGIN - 230, $y, 'Zwischensumme Positionen:', 9);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->rentalNetTotal, 2, ',', '.') . ' EUR', 9);
        $y -= 14;

        // Freitext-Posten (orderedRows()-Konzept): erscheinen in der Tabelle
        // oben zusammen mit den Mietpositionen. Nutzerwunsch
        // (Zubehörpauschale auch auf Freitext-Positionen): fließen
        // zusammen mit den Mietpreisen VOR der Zubehörpauschale/dem
        // Kombirabatt in die Basis ein (siehe DocumentTotals::calculate()),
        // werden hier aber weiterhin als eigene Zwischensumme ausgewiesen.
        if ($detail->freeTextTotal() > 0) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Freitext-Posten:', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($detail->freeTextTotal(), 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        $orderConfirmation = $detail->orderConfirmation;
        if ($orderConfirmation->accessoryFlatRateEnabled && $orderConfirmation->accessoryFlatRatePercent !== null) {
            $writer->text($pageWidth - self::MARGIN - 230, $y, 'Zubehörpauschale (' . number_format((float) $orderConfirmation->accessoryFlatRatePercent, 2, ',', '.') . '%):', 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '+' . number_format($totals->accessoryFlatRateAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        if ($orderConfirmation->discountType !== null && $orderConfirmation->discountValue !== null) {
            $discountLabel = $orderConfirmation->discountType === 'percent'
                ? 'Kombirabatt (' . number_format((float) $orderConfirmation->discountValue, 2, ',', '.') . '%):'
                : 'Kombirabatt (Festbetrag):';
            $writer->text($pageWidth - self::MARGIN - 230, $y, $discountLabel, 9);
            $writer->text($pageWidth - self::MARGIN - 75, $y, '-' . number_format($totals->discountAmount, 2, ',', '.') . ' EUR', 9);
            $y -= 14;
        }

        $writer->text($pageWidth - self::MARGIN - 230, $y, 'Gesamtsumme (netto):', 10, true);
        $writer->text($pageWidth - self::MARGIN - 75, $y, number_format($totals->grossTotal, 2, ',', '.') . ' EUR', 10, true);
        $y -= 30;

        // Dokumenteigener Schlusstext (OrderConfirmation::$closingText) —
        // siehe Klassen-Doc oben: einziger Rich-Text-Anwendungsfall dieser
        // Klasse, identisches Rendering-Muster wie in Invoice-/
        // OfferPdfGenerator::generate().
        $documentClosingBlocks = $this->richTextRenderer->parse($orderConfirmation->closingText);
        if ($documentClosingBlocks !== []) {
            $documentClosingPageNumber = $pageNumber;
            $y = $this->richTextRenderer->render(
                $writer,
                $documentClosingBlocks,
                self::MARGIN,
                $y - 10.0,
                $pageWidth - 2 * self::MARGIN,
                self::MARGIN + 20,
                function () use ($writer, $detail, $companySettings, $pageWidth, $pageHeight, $pdfDesignSettings, &$documentClosingPageNumber): float {
                    $documentClosingPageNumber++;
                    $writer->addPage();

                    return $this->drawHeader($writer, $detail, $companySettings, null, null, $pageWidth, $pageHeight, $documentClosingPageNumber, continuation: true, pdfDesignSettings: $pdfDesignSettings);
                }
            );
        }

        return $writer->output();
    }

    /** @return array<int, array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool}> */
    private function buildRows(OrderConfirmationDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];

        foreach ($detail->orderedRows() as $row) {
            if ($row->type === OrderConfirmationRow::TYPE_SECTION) {
                /** @var OrderConfirmationSectionText $sectionText */
                $sectionText = $row->data;
                $rows[] = [
                    'nameLines' => $this->wrapText($sectionText->heading, $columns['article']['width'], self::TABLE_FONT_SIZE),
                    'quantity' => '',
                    'unitPrice' => '',
                    'lineTotal' => '',
                    'isSection' => true,
                ];

                continue;
            }

            if ($row->type === OrderConfirmationRow::TYPE_FREE_TEXT) {
                /** @var OrderConfirmationFreeTextItem $item */
                $item = $row->data;
                $rows[] = [
                    'nameLines' => $this->wrapText($item->description, $columns['article']['width'], self::TABLE_FONT_SIZE),
                    'quantity' => rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ','),
                    'unitPrice' => number_format((float) $item->unitPrice, 2, ',', '.') . ' EUR',
                    'lineTotal' => number_format($item->lineTotal(), 2, ',', '.') . ' EUR',
                    'isSection' => false,
                ];

                continue;
            }

            $entry = $row->data;
            $rows[] = [
                'nameLines' => $this->wrapText($entry->displayName(), $columns['article']['width'], self::TABLE_FONT_SIZE),
                'quantity' => (string) $entry->item->quantity,
                'unitPrice' => number_format((float) $entry->item->unitPricePerDay, 2, ',', '.') . ' EUR',
                'lineTotal' => number_format($entry->lineTotal(), 2, ',', '.') . ' EUR',
                'isSection' => false,
            ];
        }

        return $rows;
    }

    private function drawHeader(
        SimplePdfWriter $writer,
        OrderConfirmationDetail $detail,
        ?CompanySettings $companySettings,
        ?string $logoJpeg,
        ?array $logoBox,
        float $pageWidth,
        float $pageHeight,
        int $pageNumber,
        bool $continuation = false,
        ?PdfDesignSettings $pdfDesignSettings = null,
        ?PdfLayoutSettings $layout = null
    ): float {
        $this->drawFoldMarks($writer, $pageHeight);

        if ($logoJpeg !== null && $logoBox !== null) {
            $logoOverride = $layout?->get('logo');
            if ($logoOverride !== null) {
                $logoPoint = $logoOverride->toPdfPoint($pageHeight);
                $writer->image($logoJpeg, $logoPoint['x'], $logoPoint['y'] - $logoBox[1], $logoBox[0], $logoBox[1]);
            } else {
                $logoX = $pdfDesignSettings?->logoPosition === PdfDesignSettings::LOGO_POSITION_LEFT
                    ? self::MARGIN
                    : $pageWidth - self::MARGIN - $logoBox[0];
                $writer->image($logoJpeg, $logoX, $pageHeight - self::MARGIN - $logoBox[1], $logoBox[0], $logoBox[1]);
            }
        }

        if (!$continuation && $companySettings !== null) {
            $this->drawWindowEnvelopeAddress($writer, $detail, $companySettings, $pageHeight, $layout);
        }

        $y = $continuation
            ? $pageHeight - self::MARGIN
            : $pageHeight - (self::WINDOW_ZONE_BOTTOM_MM * self::MM_TO_PT) - 10.0;

        $titleOverride = !$continuation ? $layout?->get('title') : null;
        $titleX = self::MARGIN;
        if ($titleOverride !== null) {
            $titlePoint = $titleOverride->toPdfPoint($pageHeight);
            $titleX = $titlePoint['x'];
            $y = $titlePoint['y'];
        }
        $title = $continuation ? 'Auftragsbestätigung (Fortsetzung)' : 'Auftragsbestätigung';
        $titleFontSize = $titleOverride?->fontSize ?? 18.0;
        $writer->text($titleX, $y - 4, $title, $titleFontSize, true, color: $pdfDesignSettings?->accentColorPdfString() ?? '0 0 0');
        $y -= 30;

        if (!$continuation) {
            $infoLinesOverride = $layout?->get('info_lines');
            $infoLinesX = self::MARGIN;
            $infoLinesFontSize = 10.0;
            if ($infoLinesOverride !== null) {
                $infoPoint = $infoLinesOverride->toPdfPoint($pageHeight);
                $infoLinesX = $infoPoint['x'];
                $y = $infoPoint['y'];
                $infoLinesFontSize = $infoLinesOverride->fontSize ?? 10.0;
            }

            foreach ($this->buildInfoLines($detail) as $line) {
                $writer->text($infoLinesX, $y, $line, $infoLinesFontSize);
                $y -= 14;
            }
        }

        $writer->text($pageWidth - self::MARGIN - 50, $pageHeight - self::MARGIN - 60, 'Seite ' . $pageNumber, 8);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, $pdfDesignSettings?->accentColorLinePdfString() ?? '0.4 0.4 0.4');
        $y -= 20;

        return $y;
    }

    /** @return string[] */
    private function buildInfoLines(OrderConfirmationDetail $detail): array
    {
        $orderConfirmation = $detail->orderConfirmation;
        $lines = [];
        $lines[] = 'Auftragsbestätigungsnummer: ' . $orderConfirmation->orderConfirmationNumber;
        $lines[] = 'Datum: ' . $orderConfirmation->createdAt->format('d.m.Y');

        if ($orderConfirmation->eventLocation !== null && trim($orderConfirmation->eventLocation) !== '') {
            $lines[] = 'Ort: ' . $orderConfirmation->eventLocation;
        }

        // Siehe InvoicePdfGenerator für die identische Begründung (Zeitraum
        // optional seit Migration 102).
        if ($orderConfirmation->startDate !== null && $orderConfirmation->endDate !== null) {
            $lines[] = 'Zeitraum: ' . $orderConfirmation->startDate->format('d.m.Y') . ' - ' . $orderConfirmation->endDate->format('d.m.Y');
        }

        if ($orderConfirmation->note !== null && trim($orderConfirmation->note) !== '') {
            $lines[] = 'Anmerkung: ' . $orderConfirmation->note;
        }

        return $lines;
    }

    private function drawFoldMarks(SimplePdfWriter $writer, float $pageHeight): void
    {
        foreach (self::FOLD_MARK_AT_MM as $mmFromTop) {
            $y = $pageHeight - ($mmFromTop * self::MM_TO_PT);
            $writer->line(0.0, $y, self::FOLD_MARK_LENGTH_MM * self::MM_TO_PT, $y, 0.5, '0.5 0.5 0.5');
        }
    }

    private function drawWindowEnvelopeAddress(SimplePdfWriter $writer, OrderConfirmationDetail $detail, CompanySettings $companySettings, float $pageHeight, ?PdfLayoutSettings $layout = null): void
    {
        $zoneX = self::WINDOW_ZONE_LEFT_MM * self::MM_TO_PT;
        $zoneTopY = $pageHeight - (self::WINDOW_ZONE_TOP_MM * self::MM_TO_PT);

        $returnLine = $this->buildReturnAddressLine($companySettings);
        $returnOverride = $layout?->get('return_address');
        $returnX = $zoneX;
        $returnFontSize = 7.5;
        $y = $zoneTopY - 7.0;
        if ($returnOverride !== null) {
            $returnPoint = $returnOverride->toPdfPoint($pageHeight);
            $returnX = $returnPoint['x'];
            $y = $returnPoint['y'];
            $returnFontSize = $returnOverride->fontSize ?? 7.5;
        }
        if ($returnLine !== null) {
            $writer->text($returnX, $y, $returnLine, $returnFontSize);
        }
        $y -= 18.0;

        $addressOverride = $layout?->get('customer_address');
        $addressX = $zoneX;
        $addressFontSize = 10.0;
        if ($addressOverride !== null) {
            $addressPoint = $addressOverride->toPdfPoint($pageHeight);
            $addressX = $addressPoint['x'];
            $y = $addressPoint['y'];
            $addressFontSize = $addressOverride->fontSize ?? 10.0;
        }

        foreach ($this->buildCustomerAddressLines($detail->customer, $detail->contact) as $line) {
            $writer->text($addressX, $y, $line, $addressFontSize);
            $y -= 12.5;
        }
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

    /**
     * Ansprechpartner-Auswahl (Migration 104): siehe
     * InvoicePdfGenerator::buildCustomerAddressLines() für die identische
     * Begründung/Semantik.
     *
     * @return string[]
     */
    private function buildCustomerAddressLines(?Customer $customer, ?CustomerContact $contact = null): array
    {
        if ($customer === null) {
            return ['Noch nicht zugeordnet'];
        }

        $lines = [$customer->displayName()];

        $useContactAddress = $contact !== null && $contact->hasOwnAddress();
        if ($contact !== null) {
            $lines[] = $contact->name;
        }

        $addressLine2 = $useContactAddress ? $contact->addressLine2 : $customer->addressLine2;
        $street = $useContactAddress ? $contact->street : $customer->street;
        $postalCode = $useContactAddress ? $contact->postalCode : $customer->postalCode;
        $city = $useContactAddress ? $contact->city : $customer->city;

        if ($addressLine2 !== null && trim($addressLine2) !== '') {
            $lines[] = trim($addressLine2);
        }
        if ($street !== null && trim($street) !== '') {
            $lines[] = trim($street);
        }
        $cityLine = trim(($postalCode ?? '') . ' ' . ($city ?? ''));
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }

        return $lines;
    }

    /** @return array{article: array{x: float, width: float}, quantity: array{x: float, width: float}, unitPrice: array{x: float, width: float}, lineTotal: array{x: float, width: float}} */
    private function columnLayout(): array
    {
        return [
            'article' => ['x' => self::MARGIN, 'width' => 250.0],
            'quantity' => ['x' => self::MARGIN + 260.0, 'width' => 55.0],
            'unitPrice' => ['x' => self::MARGIN + 325.0, 'width' => 90.0],
            'lineTotal' => ['x' => self::MARGIN + 425.0, 'width' => 90.0],
        ];
    }

    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, ?PdfDesignSettings $pdfDesignSettings = null): float
    {
        $columns = $this->columnLayout();

        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, $pdfDesignSettings?->accentColorTintPdfString() ?? '0.92 0.92 0.92');
        $writer->text($columns['article']['x'] + 2, $y - 8, 'Bezeichnung', 9, true);
        $writer->text($columns['quantity']['x'] + 2, $y - 8, 'Menge', 9, true);
        $writer->text($columns['unitPrice']['x'] + 2, $y - 8, 'Einzelpreis', 9, true);
        $writer->text($columns['lineTotal']['x'] + 2, $y - 8, 'Summe', 9, true);

        return $y - 22;
    }

    /** @param array{nameLines: string[], quantity: string, unitPrice: string, lineTotal: string, isSection: bool} $row */
    private function drawRow(SimplePdfWriter $writer, float $topY, array $row): void
    {
        $columns = $this->columnLayout();
        $baseline = $topY - self::TABLE_LINE_HEIGHT + 2;

        foreach ($row['nameLines'] as $i => $line) {
            $writer->text($columns['article']['x'] + 2, $baseline - $i * self::TABLE_LINE_HEIGHT, $line, self::TABLE_FONT_SIZE, $row['isSection']);
        }

        if (!$row['isSection']) {
            $writer->text($columns['quantity']['x'] + 2, $baseline, $row['quantity'], self::TABLE_FONT_SIZE);
            $writer->text($columns['unitPrice']['x'] + 2, $baseline, $row['unitPrice'], self::TABLE_FONT_SIZE);
            $writer->text($columns['lineTotal']['x'] + 2, $baseline, $row['lineTotal'], self::TABLE_FONT_SIZE);
        }
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

        $wrapped = wordwrap($text, $maxChars, "\n", true);

        return explode("\n", $wrapped);
    }

    private function prepareLogoJpeg(CompanySettings $companySettings): ?string
    {
        if ($companySettings->logoFilename === null || !extension_loaded('gd')) {
            return null;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $companySettings->logoFilename);
        if (!is_file($absolutePath)) {
            return null;
        }

        $data = @file_get_contents($absolutePath);
        if ($data === false || $data === '') {
            return null;
        }

        $source = @imagecreatefromstring($data);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);

            return null;
        }

        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpegBytes = ob_get_clean();
        imagedestroy($canvas);

        return $jpegBytes === false || $jpegBytes === '' ? null : $jpegBytes;
    }

    /** @return array{0: float, 1: float} */
    private function fitBox(string $jpegBytes, float $maxWidth, float $maxHeight): array
    {
        $size = @getimagesizefromstring($jpegBytes);
        if ($size === false) {
            return [$maxWidth, $maxHeight];
        }

        [$pixelWidth, $pixelHeight] = $size;
        if ($pixelWidth <= 0 || $pixelHeight <= 0) {
            return [$maxWidth, $maxHeight];
        }

        $scale = min($maxWidth / $pixelWidth, $maxHeight / $pixelHeight);

        return [$pixelWidth * $scale, $pixelHeight * $scale];
    }
}
