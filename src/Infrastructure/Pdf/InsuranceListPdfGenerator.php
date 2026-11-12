<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Insurance\Query\InsuranceListRow;
use App\Application\Insurance\Query\RentalInsuranceList;
use App\Domain\Insurance\InsuranceField;
use App\Domain\Settings\CompanySettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Erzeugt die Versicherungsliste einer Veranstaltung als PDF: gleicher
 * Aufbau wie die Packliste (Logo rechts oben, Titel, Veranstaltungsdaten,
 * graue Tabellenkopfzeile), Spalten nach Auswahl des Benutzers, am Ende
 * die Gesamtsummen der Zeitwerte und Anschaffungspreise. Fehlende Werte
 * bleiben leer und zählen nicht zur Summe.
 *
 * Kümmert sich nur um das fachliche Layout; die PDF-Mechanik steckt in
 * SimplePdfWriter (siehe PackingListPdfGenerator für dasselbe Muster).
 */
final class InsuranceListPdfGenerator
{
    private const MARGIN = 40.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;
    private const TABLE_FONT_SIZE = 8.5;
    private const TABLE_LINE_HEIGHT = 11.0;
    private const TABLE_ROW_PADDING = 5.0;
    private const SUMMARY_HEIGHT = 70.0;

    /** Relative Spaltenbreiten je Feld (werden auf die Seitenbreite verteilt). */
    private const COLUMN_WEIGHTS = [
        'category' => 1.3,
        'manufacturer_model' => 2.5,
        'inventory_number' => 1.3,
        'acquisition_year' => 0.85,
        'current_value' => 1.15,
        'purchase_price' => 1.35,
    ];

    public function __construct(
        private readonly ?LocalFileStorage $fileStorage = null,
        private readonly ?PdfAmountFormatter $amountFormatter = null,
    ) {
    }

    /** @param list<InsuranceField> $fields */
    public function generate(RentalInsuranceList $list, array $fields, ?CompanySettings $companySettings = null): string
    {
        if ($fields === []) {
            $fields = InsuranceField::cases();
        }

        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();
        $columns = $this->columnLayout($pageWidth, $fields);

        $logoJpeg = $companySettings !== null ? $this->prepareLogoJpeg($companySettings) : null;
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $list, $logoJpeg, $pageWidth, $pageHeight, $pageNumber);
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns);

        if ($list->rows === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Für diese Veranstaltung sind keine Geräte erfasst.', 10);
            $y -= 2 * self::TABLE_LINE_HEIGHT;
        }

        foreach ($list->rows as $index => $row) {
            $cells = $this->cells($row, $columns);
            $lineCount = max(1, ...array_map(static fn (array $lines) => count($lines), $cells));
            $rowHeight = $lineCount * self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 20) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $list, null, $pageWidth, $pageHeight, $pageNumber, continuation: true);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns);
            }

            if ($index % 2 === 1) {
                $writer->filledRect(self::MARGIN, $y - $rowHeight + 1, $pageWidth - 2 * self::MARGIN, $rowHeight, '0.975 0.975 0.975');
            }
            $this->drawRow($writer, $y, $cells, $columns);
            $y -= $rowHeight;
        }

        if ($y - self::SUMMARY_HEIGHT < self::MARGIN) {
            $pageNumber++;
            $writer->addPage();
            $y = $this->drawHeader($writer, $list, null, $pageWidth, $pageHeight, $pageNumber, continuation: true);
        }
        $this->drawSummary($writer, $y - 6, $pageWidth, $list);

        return $writer->output();
    }

    /** Zeichnet Logo, Titel und Veranstaltungsdaten und liefert die y-Position darunter. */
    private function drawHeader(
        SimplePdfWriter $writer,
        RentalInsuranceList $list,
        ?string $logoJpeg,
        float $pageWidth,
        float $pageHeight,
        int $pageNumber,
        bool $continuation = false,
    ): float {
        $logoBottomY = null;
        if ($logoJpeg !== null) {
            [$logoWidth, $logoHeight] = $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT);
            $writer->image($logoJpeg, $pageWidth - self::MARGIN - $logoWidth, $pageHeight - self::MARGIN - $logoHeight, $logoWidth, $logoHeight);
            $logoBottomY = $pageHeight - self::MARGIN - $logoHeight;
        }

        $y = $pageHeight - self::MARGIN;
        $title = $continuation ? 'Versicherungsliste (Fortsetzung)' : 'Versicherungsliste';
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y - 4, $title, 18.0, true);
        $y -= 30;

        $rental = $list->rental;
        if (!$continuation) {
            $lines = [
                'Veranstaltung: ' . $rental->eventName,
                'Kunde: ' . $list->customer->displayName(),
            ];
            if ($rental->eventLocation !== null && trim($rental->eventLocation) !== '') {
                $lines[] = 'Ort: ' . $rental->eventLocation;
            }
            $lines[] = 'Zeitraum: ' . $rental->startDate->format('d.m.Y') . ' - ' . $rental->endDate->format('d.m.Y');
            $lines[] = 'Anzahl Geräte: ' . count($list->rows) . ' · Stand: ' . (new \DateTimeImmutable('today'))->format('d.m.Y');
            foreach ($lines as $line) {
                $writer->text(self::MARGIN, $y, $line, 10);
                $y -= 14;
            }
        } else {
            $writer->text(self::MARGIN, $y, $rental->eventName, 10);
            $y -= 14;
        }

        if ($logoBottomY !== null) {
            $y = min($y, $logoBottomY - 10);
        }

        $writer->textRight($pageWidth - self::MARGIN, $y, 'Seite ' . $pageNumber, 8);
        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, '0.4 0.4 0.4');

        return $y - 20;
    }

    /**
     * @param list<InsuranceField> $fields
     * @return list<array{field: InsuranceField, x: float, width: float}>
     */
    private function columnLayout(float $pageWidth, array $fields): array
    {
        $available = $pageWidth - 2 * self::MARGIN;
        $totalWeight = array_sum(array_map(static fn (InsuranceField $f) => self::COLUMN_WEIGHTS[$f->value], $fields));

        $columns = [];
        $x = self::MARGIN;
        foreach ($fields as $field) {
            $width = $available * self::COLUMN_WEIGHTS[$field->value] / $totalWeight;
            $columns[] = ['field' => $field, 'x' => $x, 'width' => $width];
            $x += $width;
        }

        return $columns;
    }

    /** @param list<array{field: InsuranceField, x: float, width: float}> $columns */
    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, array $columns): float
    {
        $previousFontRole = $writer->setFontRole(SimplePdfWriter::ROLE_TABLE);
        try {
            $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, '0.92 0.92 0.92');
            foreach ($columns as $column) {
                $label = $this->headerLabel($column['field']);
                if ($column['field']->isAmount()) {
                    $writer->textRight($column['x'] + $column['width'] - 4, $y - 8, $label, 9, true);
                } else {
                    $writer->text($column['x'] + 2, $y - 8, $label, 9, true);
                }
            }

            return $y - 22;
        } finally {
            $writer->setFontRole($previousFontRole);
        }
    }

    /** Kurze Spaltenüberschriften, damit sie auch bei allen sechs Spalten in eine Zeile passen. */
    private function headerLabel(InsuranceField $field): string
    {
        return match ($field) {
            InsuranceField::Category => 'Kategorie',
            InsuranceField::ManufacturerModel => 'Hersteller / Modell',
            InsuranceField::InventoryNumber => 'Inventarnr.',
            InsuranceField::AcquisitionYear => 'Anschaffung',
            InsuranceField::CurrentValue => 'Zeitwert',
            InsuranceField::PurchasePrice => 'Anschaff.-Preis',
        };
    }

    /**
     * @param list<array{field: InsuranceField, x: float, width: float}> $columns
     * @return list<string[]> je Spalte die (umgebrochenen) Zeilen
     */
    private function cells(InsuranceListRow $row, array $columns): array
    {
        $cells = [];
        foreach ($columns as $column) {
            $value = $row->value($column['field']);
            if ($value === null) {
                $cells[] = [''];
            } elseif ($column['field']->isAmount()) {
                $cells[] = [$this->amount($value)];
            } else {
                $cells[] = $this->wrapText($value, $column['width'] - 6, self::TABLE_FONT_SIZE);
            }
        }

        return $cells;
    }

    /**
     * @param list<string[]> $cells
     * @param list<array{field: InsuranceField, x: float, width: float}> $columns
     */
    private function drawRow(SimplePdfWriter $writer, float $topY, array $cells, array $columns): void
    {
        $previousFontRole = $writer->setFontRole(SimplePdfWriter::ROLE_TABLE);
        try {
            $baseline = $topY - self::TABLE_LINE_HEIGHT + 2;
            foreach ($columns as $i => $column) {
                foreach ($cells[$i] as $lineIndex => $line) {
                    $lineY = $baseline - $lineIndex * self::TABLE_LINE_HEIGHT;
                    if ($column['field']->isAmount()) {
                        $writer->textRight($column['x'] + $column['width'] - 4, $lineY, $line, self::TABLE_FONT_SIZE);
                    } else {
                        $writer->text($column['x'] + 2, $lineY, $line, self::TABLE_FONT_SIZE);
                    }
                }
            }
        } finally {
            $writer->setFontRole($previousFontRole);
        }
    }

    /** Summenblock rechtsbündig unter der Tabelle (immer beide Summen, unabhängig von der Spaltenauswahl). */
    private function drawSummary(SimplePdfWriter $writer, float $y, float $pageWidth, RentalInsuranceList $list): void
    {
        $right = $pageWidth - self::MARGIN;
        $boxWidth = 260.0;
        $left = $right - $boxWidth;

        $writer->line(self::MARGIN, $y, $right, $y, 1.0, '0.4 0.4 0.4');
        $y -= 6;

        $writer->filledRect($left, $y - 36, $boxWidth, 36, '0.92 0.92 0.92');
        $writer->text($left + 8, $y - 14, 'Gesamtsumme Zeitwert', 10, true);
        $writer->textRight($right - 8, $y - 14, $this->amount($list->totalCurrentValue()), 10, true);
        $writer->text($left + 8, $y - 29, 'Gesamtsumme Anschaffungspreise', 10, true);
        $writer->textRight($right - 8, $y - 29, $this->amount($list->totalPurchasePrice()), 10, true);
        $y -= 48;

        $hints = [];
        if (($missing = $list->countMissingCurrentValues()) > 0) {
            $hints[] = $missing . ($missing === 1 ? ' Gerät' : ' Geräte') . ' ohne Zeitwert';
        }
        if (($missing = $list->countMissingPurchasePrices()) > 0) {
            $hints[] = $missing . ($missing === 1 ? ' Gerät' : ' Geräte') . ' ohne Anschaffungspreis';
        }
        if ($hints !== []) {
            $writer->textRight($right, $y, implode(' · ', $hints) . ' (in den Summen nicht enthalten)', 8, false, true, '0.35 0.35 0.35');
            $y -= 12;
        }
        $writer->textRight($right, $y, 'Zeitwerte sind Schätzwerte ohne Gewähr.', 8, false, true, '0.35 0.35 0.35');
    }

    private function amount(string $value): string
    {
        return $this->amountFormatter?->format((float) $value) ?? number_format((float) $value, 2, ',', '.') . ' €';
    }

    /** @return string[] Grobe, breitenbasierte Umbruch-Näherung (siehe PackingListPdfGenerator::wrapText()). */
    private function wrapText(string $text, float $maxWidth, float $fontSize): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }
        $maxChars = max(4, (int) floor($maxWidth / ($fontSize * 0.52)));

        return explode("\n", wordwrap($text, $maxChars, "\n", true));
    }

    /** Siehe PackingListPdfGenerator::prepareLogoJpeg() — bewusst dupliziert (Projektkonvention je PDF-Generator). */
    private function prepareLogoJpeg(CompanySettings $companySettings): ?string
    {
        if ($this->fileStorage === null || $companySettings->logoFilename === null || !extension_loaded('gd')) {
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
