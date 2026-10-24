<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Rental\Query\RentalDetail;
use App\Domain\Settings\CompanySettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Erzeugt die "Packliste" einer Veranstaltung (Vermietung) als PDF: Firmenlogo
 * oben rechts, Veranstaltungs-Kopfdaten und eine Tabelle der geplanten
 * Artikel (Menge, Ausgabestand, ausgegebene Inventarnummern), spiegelt dabei
 * dieselben Felder wie die "Geplante Artikel"-Liste auf der
 * Veranstaltungs-Detailseite (resources/views/rentals/show.php).
 *
 * Kümmert sich nur um das fachliche Layout dieses konkreten Dokuments; die
 * eigentliche PDF-Schreib-Mechanik steckt in SimplePdfWriter.
 */
final class PackingListPdfGenerator
{
    private const MARGIN = 40.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;
    private const TABLE_FONT_SIZE = 8.5;
    private const TABLE_LINE_HEIGHT = 11.0;
    private const TABLE_ROW_PADDING = 6.0;
    private const CHECKBOX_COLUMN_WIDTH = 16.0;
    private const CHECKBOX_RADIUS = 4.5;

    public function __construct(private readonly LocalFileStorage $fileStorage)
    {
    }

    public function generate(RentalDetail $detail, CompanySettings $companySettings): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $logoJpeg = $this->prepareLogoJpeg($companySettings);
        $logoBox = $logoJpeg !== null ? $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT) : null;

        $rows = $this->buildRows($detail);
        $pageNumber = 1;

        $writer->addPage();
        $y = $this->drawHeader($writer, $detail, $logoJpeg, $logoBox, $pageWidth, $pageHeight, $pageNumber);
        $y = $this->drawTableHeader($writer, $y, $pageWidth);

        if ($rows === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Keine Artikel geplant.', 10);
        }

        foreach ($rows as $row) {
            $rowLineCount = max(count($row['nameLines']), count($row['numbersLines']), 1);
            $rowHeight = $rowLineCount * self::TABLE_LINE_HEIGHT + self::TABLE_ROW_PADDING;

            if ($y - $rowHeight < self::MARGIN + 20) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $detail, null, null, $pageWidth, $pageHeight, $pageNumber, continuation: true);
                $y = $this->drawTableHeader($writer, $y, $pageWidth);
            }

            $this->drawRow($writer, $y, $row);
            $y -= $rowHeight;
        }

        return $writer->output();
    }

    /**
     * @return array<int, array{nameLines: string[], numbersLines: string[], quantity: string, issued: string}>
     */
    private function buildRows(RentalDetail $detail): array
    {
        $columns = $this->columnLayout();
        $rows = [];

        foreach ($detail->items as $entry) {
            $issuedNumbers = array_values(array_map(
                fn ($issued) => $issued->inventoryNumber->number,
                array_filter($detail->issuedItems, fn ($issued) => $issued->article->id === $entry->article->id)
            ));

            $rows[] = [
                'nameLines' => $this->wrapText($entry->article->name, $columns['article']['width'], self::TABLE_FONT_SIZE),
                'numbersLines' => $this->wrapText(implode(', ', $issuedNumbers), $columns['numbers']['width'], self::TABLE_FONT_SIZE),
                'quantity' => (string) $entry->item->quantity,
                'issued' => (string) $entry->issuedCount,
            ];
        }

        return $rows;
    }

    /** Zeichnet den oberen Bereich (Logo, Titel, Veranstaltungsdaten) und liefert die y-Position darunter. */
    private function drawHeader(
        SimplePdfWriter $writer,
        RentalDetail $detail,
        ?string $logoJpeg,
        ?array $logoBox,
        float $pageWidth,
        float $pageHeight,
        int $pageNumber,
        bool $continuation = false
    ): float {
        if ($logoJpeg !== null && $logoBox !== null) {
            $writer->image(
                $logoJpeg,
                $pageWidth - self::MARGIN - $logoBox[0],
                $pageHeight - self::MARGIN - $logoBox[1],
                $logoBox[0],
                $logoBox[1]
            );
        }

        $y = $pageHeight - self::MARGIN;
        $title = $continuation ? 'Packliste (Fortsetzung)' : 'Packliste';
        $writer->text(self::MARGIN, $y - 4, $title, 18, true);
        $y -= 30;

        if (!$continuation) {
            foreach ($this->buildEventInfoLines($detail) as $line) {
                $writer->text(self::MARGIN, $y, $line, 10);
                $y -= 14;
            }
        } else {
            $writer->text(self::MARGIN, $y, $detail->rental->eventName, 10);
            $y -= 14;
        }

        $writer->text($pageWidth - self::MARGIN - 50, $pageHeight - self::MARGIN - 60, 'Seite ' . $pageNumber, 8);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, '0.4 0.4 0.4');
        $y -= 20;

        return $y;
    }

    /** @return string[] */
    private function buildEventInfoLines(RentalDetail $detail): array
    {
        $rental = $detail->rental;
        $lines = [];
        $lines[] = 'Veranstaltung: ' . $rental->eventName;
        $lines[] = 'Kunde: ' . $detail->customer->displayName();

        if ($rental->eventLocation !== null && trim($rental->eventLocation) !== '') {
            $lines[] = 'Ort: ' . $rental->eventLocation;
        }

        $lines[] = 'Zeitraum: ' . $rental->startDate->format('d.m.Y') . ' - ' . $rental->endDate->format('d.m.Y');

        return $lines;
    }

    /**
     * @return array{
     *     checkbox: array{x: float, width: float},
     *     article: array{x: float, width: float},
     *     quantity: array{x: float, width: float},
     *     issued: array{x: float, width: float},
     *     numbers: array{x: float, width: float}
     * }
     */
    private function columnLayout(): array
    {
        // Vor der Artikelspalte eine schmale Kontrollkästchen-Spalte (siehe
        // drawRow()/SimplePdfWriter::drawCircle()) — das Artikel-Feld beginnt
        // entsprechend versetzt, mit etwas Abstand zum Kreis, damit die Zeile
        // weiterhin sauber wirkt.
        $checkboxColumnX = self::MARGIN;
        $articleColumnX = $checkboxColumnX + self::CHECKBOX_COLUMN_WIDTH;

        return [
            'checkbox' => ['x' => $checkboxColumnX, 'width' => self::CHECKBOX_COLUMN_WIDTH],
            'article' => ['x' => $articleColumnX, 'width' => 204.0],
            'quantity' => ['x' => self::MARGIN + 230.0, 'width' => 55.0],
            'issued' => ['x' => self::MARGIN + 295.0, 'width' => 65.0],
            'numbers' => ['x' => self::MARGIN + 370.0, 'width' => 145.0],
        ];
    }

    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth): float
    {
        $columns = $this->columnLayout();

        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, '0.92 0.92 0.92');
        $writer->text($columns['article']['x'] + 2, $y - 8, 'Artikel', 9, true);
        $writer->text($columns['quantity']['x'] + 2, $y - 8, 'Geplant', 9, true);
        $writer->text($columns['issued']['x'] + 2, $y - 8, 'Ausgegeben', 9, true);
        $writer->text($columns['numbers']['x'] + 2, $y - 8, 'Inventarnummern', 9, true);

        return $y - 22;
    }

    /** @param array{nameLines: string[], numbersLines: string[], quantity: string, issued: string} $row */
    private function drawRow(SimplePdfWriter $writer, float $topY, array $row): void
    {
        $columns = $this->columnLayout();
        $baseline = $topY - self::TABLE_LINE_HEIGHT + 2;

        // Leerer Kreis zum handschriftlichen Abhaken beim Packen, vertikal
        // auf der (ggf. mehrzeiligen) Zeile zentriert.
        $rowLineCount = max(count($row['nameLines']), count($row['numbersLines']), 1);
        $rowHeight = $rowLineCount * self::TABLE_LINE_HEIGHT;
        $circleCenterX = $columns['checkbox']['x'] + self::CHECKBOX_COLUMN_WIDTH / 2 - 2;
        $circleCenterY = $topY - $rowHeight / 2 + self::TABLE_LINE_HEIGHT / 2;
        $writer->drawCircle($circleCenterX, $circleCenterY, self::CHECKBOX_RADIUS);

        foreach ($row['nameLines'] as $i => $line) {
            $writer->text($columns['article']['x'] + 2, $baseline - $i * self::TABLE_LINE_HEIGHT, $line, self::TABLE_FONT_SIZE);
        }

        $writer->text($columns['quantity']['x'] + 2, $baseline, $row['quantity'], self::TABLE_FONT_SIZE);
        $writer->text($columns['issued']['x'] + 2, $baseline, $row['issued'], self::TABLE_FONT_SIZE);

        foreach ($row['numbersLines'] as $i => $line) {
            $writer->text($columns['numbers']['x'] + 2, $baseline - $i * self::TABLE_LINE_HEIGHT, $line, self::TABLE_FONT_SIZE);
        }
    }

    /** @return string[] Grobe, breitenbasierte Umbruch-Näherung (kein exaktes Font-Metrik-Kerning nötig, nur "kein Überlauf"). */
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

    /** Lädt das Firmenlogo (falls vorhanden) und rastert es via GD als JPEG auf weißem Hintergrund, unabhängig vom Ursprungsformat (PNG/JPEG/…). */
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

    /** @return array{0: float, 1: float} Zielgröße in PDF-Punkten, seitenverhältnistreu in die Box eingepasst. */
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
