<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Article\Query\ArticleListItem;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\PdfLayout\PdfLayoutSettings;
use App\Domain\Settings\PriceRoundingDirection;
use App\Domain\Settings\PriceRoundingStep;
use App\Domain\Settings\PriceTier;
use App\Domain\Settings\RentalPriceCalculator;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Erzeugt die Preisliste (abrechenbare Artikel × Einsatztage-Staffelung) als
 * PDF-Tabelle: eine Zeile pro Artikel, eine Spalte pro Preisstaffel-Eintrag
 * (siehe App\Domain\Settings\PriceTier). Die Spaltenanzahl richtet sich
 * bewusst dynamisch nach count($tiers) statt fest auf 7 zu setzen, damit eine
 * spätere Erweiterung der Preisstaffelung (mehr als 7 Einsatztage) hier ohne
 * Anpassung funktioniert.
 *
 * Kümmert sich nur um das fachliche Layout dieses konkreten Dokuments; die
 * eigentliche PDF-Schreib-Mechanik steckt in SimplePdfWriter (siehe auch
 * PackingListPdfGenerator für dasselbe Muster).
 */
final class PriceListPdfGenerator
{
    private const MARGIN = 40.0;
    private const TABLE_FONT_SIZE = 8.5;
    private const TABLE_LINE_HEIGHT = 12.0;
    private const NAME_COLUMN_WIDTH = 200.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;

    public function __construct(private readonly ?LocalFileStorage $fileStorage = null)
    {
    }

    /**
     * @param ArticleListItem[] $articles nur abrechenbare Artikel (isBillable), bereits alphabetisch sortiert, mit aufgelöstem Hersteller
     * @param PriceTier[]       $tiers
     */
    public function generate(
        array $articles,
        array $tiers,
        ?PdfLayoutSettings $layout = null,
        PriceRoundingStep $roundingStep = PriceRoundingStep::None,
        PriceRoundingDirection $roundingDirection = PriceRoundingDirection::Nearest,
        ?CompanySettings $companySettings = null,
    ): string {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();

        $columns = $this->columnLayout($pageWidth, $tiers);
        $pageNumber = 1;

        $writer->addPage();
        // Nutzerwunsch: Firmenlogo rechts oben (nur erste Seite, wie bei
        // Angebot/Rechnung).
        $logoJpeg = $companySettings !== null ? $this->prepareLogoJpeg($companySettings) : null;
        $y = $this->drawHeader($writer, $pageWidth, $pageHeight, $pageNumber, layout: $layout, logoJpeg: $logoJpeg);
        $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns, $tiers);

        if ($articles === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Keine abrechenbaren Artikel vorhanden.', 10);
        }

        foreach ($articles as $item) {
            if ($y - self::TABLE_LINE_HEIGHT < self::MARGIN + 20) {
                $pageNumber++;
                $writer->addPage();
                $y = $this->drawHeader($writer, $pageWidth, $pageHeight, $pageNumber, continuation: true);
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns, $tiers);
            }

            $this->drawRow($writer, $y, $item, $columns, $tiers, $roundingStep, $roundingDirection);
            $y -= self::TABLE_LINE_HEIGHT;
        }

        return $writer->output();
    }

    private function drawHeader(SimplePdfWriter $writer, float $pageWidth, float $pageHeight, int $pageNumber, bool $continuation = false, ?PdfLayoutSettings $layout = null, ?string $logoJpeg = null): float
    {
        $y = $pageHeight - self::MARGIN;

        // Logo rechts oben (per Layout-Editor verschiebbar, Element "logo").
        $logoBottomY = null;
        if (!$continuation && $logoJpeg !== null) {
            [$logoWidth, $logoHeight] = $this->fitBox($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT);
            $logoOverride = $layout?->get('logo');
            if ($logoOverride !== null) {
                $logoPoint = $logoOverride->toPdfPoint($pageHeight);
                $logoX = $logoPoint['x'];
                $logoTopY = $logoPoint['y'];
            } else {
                $logoX = $pageWidth - self::MARGIN - $logoWidth;
                $logoTopY = $pageHeight - self::MARGIN;
            }
            $writer->image($logoJpeg, $logoX, $logoTopY - $logoHeight, $logoWidth, $logoHeight);
            $logoBottomY = $logoTopY - $logoHeight;
        }

        $titleOverride = !$continuation ? $layout?->get('title') : null;
        $titleX = self::MARGIN;
        if ($titleOverride !== null) {
            $titlePoint = $titleOverride->toPdfPoint($pageHeight);
            $titleX = $titlePoint['x'];
            $y = $titlePoint['y'];
        }
        $title = $continuation ? 'Preisliste (Fortsetzung)' : 'Preisliste';
        $titleFontSize = $titleOverride?->fontSize ?? 18.0;
        $writer->text($titleX, $y - 4, $title, $titleFontSize, true);
        $y -= 26;

        if (!$continuation) {
            $writer->text(self::MARGIN, $y, 'Preise pro Einsatztag in Euro, netto', 10);
            $y -= 16;
        }

        // Kopfbereich mindestens bis unter das Logo ziehen, damit Trennlinie
        // und Tabelle nicht in das Logo laufen.
        if ($logoBottomY !== null) {
            $y = min($y, $logoBottomY - 10);
        }

        // Seitenzahl rechtsbündig direkt über der Trennlinie (früher fest
        // rechts oben — dort sitzt jetzt das Logo).
        $writer->textRight($pageWidth - self::MARGIN, $y, 'Seite ' . $pageNumber, 8);

        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, '0.4 0.4 0.4');
        $y -= 20;

        return $y;
    }

    /**
     * @param PriceTier[] $tiers
     * @return array{name: array{x: float, width: float}, tiers: array<int, array{x: float, width: float}>}
     */
    private function columnLayout(float $pageWidth, array $tiers): array
    {
        $nameColumn = ['x' => self::MARGIN, 'width' => self::NAME_COLUMN_WIDTH];

        $tierAreaX = self::MARGIN + self::NAME_COLUMN_WIDTH;
        $tierAreaWidth = $pageWidth - self::MARGIN - $tierAreaX;
        $tierCount = max(count($tiers), 1);
        $tierColumnWidth = $tierAreaWidth / $tierCount;

        $tierColumns = [];
        $index = 0;
        foreach ($tiers as $tier) {
            $tierColumns[$tier->dayCount] = ['x' => $tierAreaX + $index * $tierColumnWidth, 'width' => $tierColumnWidth];
            $index++;
        }

        return ['name' => $nameColumn, 'tiers' => $tierColumns];
    }

    /**
     * @param array{name: array{x: float, width: float}, tiers: array<int, array{x: float, width: float}>} $columns
     * @param PriceTier[] $tiers
     */
    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, array $columns, array $tiers): float
    {
        $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, '0.92 0.92 0.92');
        $writer->text($columns['name']['x'] + 2, $y - 8, 'Artikel', 9, true);

        foreach ($tiers as $tier) {
            $label = $tier->dayCount . ($tier->dayCount === 1 ? ' Tag' : ' Tage');
            // Preisspalten rechtsbündig (Nutzerwunsch, gilt für alle Dokumente).
            $writer->textRight($columns['tiers'][$tier->dayCount]['x'] + $columns['tiers'][$tier->dayCount]['width'] - 4, $y - 8, $label, 9, true);
        }

        return $y - 22;
    }

    /**
     * @param array{name: array{x: float, width: float}, tiers: array<int, array{x: float, width: float}>} $columns
     * @param PriceTier[] $tiers
     */
    private function drawRow(
        SimplePdfWriter $writer,
        float $y,
        ArticleListItem $item,
        array $columns,
        array $tiers,
        PriceRoundingStep $roundingStep = PriceRoundingStep::None,
        PriceRoundingDirection $roundingDirection = PriceRoundingDirection::Nearest,
    ): void {
        $baseline = $y - self::TABLE_LINE_HEIGHT + 3;

        $name = ArticleDisplayName::of($item->article, $item->manufacturer);
        $maxChars = (int) floor($columns['name']['width'] / (self::TABLE_FONT_SIZE * 0.52));
        if (mb_strlen($name) > $maxChars) {
            $name = mb_substr($name, 0, max(1, $maxChars - 1)) . '…';
        }
        $writer->text($columns['name']['x'] + 2, $baseline, $name, self::TABLE_FONT_SIZE);

        if ($item->article->dailyRentalPrice === null) {
            return;
        }

        $prices = RentalPriceCalculator::calculate($item->article->dailyRentalPrice, $tiers, $roundingStep, $roundingDirection);
        foreach ($prices as $dayCount => $price) {
            $writer->textRight($columns['tiers'][$dayCount]['x'] + $columns['tiers'][$dayCount]['width'] - 4, $baseline, number_format((float) $price, 2, ',', '.') . ' €', self::TABLE_FONT_SIZE);
        }
    }

    /** Siehe OfferPdfGenerator::prepareLogoJpeg() — bewusst dupliziert (Projektkonvention je PDF-Generator). */
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
