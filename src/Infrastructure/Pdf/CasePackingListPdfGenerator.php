<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\CaseItem\Query\CaseInventoryNumberWithContent;
use App\Application\CaseItem\Query\CaseItemDetail;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Settings\CompanySettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Stückliste eines Case-Exemplars (Nutzerwunsch, Migration 114): Inhalt mit
 * Menge und Abhak-Kästchen, Hinweistext (WYSIWYG) und Fotos des befüllten
 * Cases, damit Kunden alle Teile wieder richtig einsortieren können.
 *
 * Aufbau wie PackingListPdfGenerator (Logo rechts oben, Seitenzahl über der
 * Trennlinie); Artikel mit Dokumentbild und Kurztext über ArticlePdfRow.
 * Das Zubehör jedes Artikels (Artikel › Zubehör) folgt direkt unter dem
 * Artikel, eingerückt, leicht eingefärbt und mit dem Hinweis „Zubehör“;
 * Menge = Zubehörmenge × Artikelmenge im Case.
 */
final class CasePackingListPdfGenerator
{
    private const MARGIN = 40.0;
    private const TABLE_FONT_SIZE = 9.0;
    private const TABLE_LINE_HEIGHT = 13.0;
    private const ROW_PADDING = 6.0;
    private const CHECKBOX_SIZE = 9.0;
    private const QUANTITY_COLUMN_WIDTH = 50.0;
    private const CHECK_COLUMN_WIDTH = 24.0;
    private const LOGO_MAX_WIDTH = 120.0;
    private const LOGO_MAX_HEIGHT = 50.0;
    private const PHOTO_GAP = 12.0;
    private const PHOTO_MAX_HEIGHT = 300.0;
    private const PHOTO_PIXELS = 1400;
    private const ACCESSORY_FILL = '0.93 0.96 0.99';
    private const ACCESSORY_LABEL_COLOR = '0.25 0.4 0.6';
    private const ACCESSORY_INDENT = 14.0;

    public function __construct(
        private readonly LocalFileStorage $fileStorage,
        private readonly RichTextPdfRenderer $richTextRenderer,
        private readonly ?ArticleAccessoryRepositoryInterface $accessories = null,
    ) {
    }

    public function generate(CaseItemDetail $detail, CaseInventoryNumberWithContent $entry, ?CompanySettings $companySettings = null): string
    {
        $writer = new SimplePdfWriter();
        $pageWidth = $writer->pageWidth();
        $pageHeight = $writer->pageHeight();
        $contentWidth = $pageWidth - 2 * self::MARGIN;
        $pageNumber = 1;
        $subtitle = $detail->case->name . ' · ' . ($entry->inventoryNumber->caseNumber ?? $entry->inventoryNumber->number);

        $writer->addPage();
        $logoJpeg = $companySettings !== null ? $this->prepareLogoJpeg($companySettings) : null;
        $y = $this->drawHeader($writer, $pageWidth, $pageHeight, $pageNumber, $detail, $entry, $logoJpeg);

        $newPage = function () use ($writer, $pageWidth, $pageHeight, &$pageNumber, $subtitle): float {
            $pageNumber++;
            $writer->addPage();

            return $this->drawContinuationHeader($writer, $pageWidth, $pageHeight, $pageNumber, $subtitle);
        };

        // --- Inhalt ---------------------------------------------------------
        $columns = [
            'check' => self::MARGIN,
            'quantity' => self::MARGIN + self::CHECK_COLUMN_WIDTH,
            'article' => self::MARGIN + self::CHECK_COLUMN_WIDTH + self::QUANTITY_COLUMN_WIDTH + 8,
        ];
        $articleWidth = $pageWidth - self::MARGIN - $columns['article'];
        $rowRenderer = new ArticlePdfRow($this->fileStorage);
        $wrap = static function (string $text, float $maxWidth, float $fontSize): array {
            $maxChars = max(6, (int) floor($maxWidth / ($fontSize * 0.52)));

            return explode("\n", wordwrap(trim($text), $maxChars, "\n", true));
        };

        $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns);
        if ($entry->content === []) {
            $writer->text(self::MARGIN, $y - self::TABLE_LINE_HEIGHT, 'Diesem Case sind noch keine Artikel zugeordnet.', 10);
            $y -= self::TABLE_LINE_HEIGHT * 2;
        }
        $previousFontRole = $writer->setFontRole(SimplePdfWriter::ROLE_TABLE);
        foreach ($entry->content as $contentEntry) {
            $row = $rowRenderer->nameColumn($contentEntry->article, [$contentEntry->displayName()], $articleWidth, self::TABLE_FONT_SIZE, $wrap);
            $height = ArticlePdfRow::contentHeight($row, self::TABLE_LINE_HEIGHT) + self::ROW_PADDING;
            if ($y - $height < self::MARGIN + 20) {
                $y = $newPage();
                $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns);
            }

            $top = $y - self::ROW_PADDING / 2;
            $baseline = $top - self::TABLE_LINE_HEIGHT + 2;
            $this->drawCheckbox($writer, $columns['check'] + 4, $baseline - 1);
            $writer->textRight($columns['quantity'] + self::QUANTITY_COLUMN_WIDTH - 6, $baseline, $contentEntry->item->quantity . ' ×', self::TABLE_FONT_SIZE, true);
            ArticlePdfRow::drawNameColumn($writer, $columns['article'], $top, $row, self::TABLE_FONT_SIZE, self::TABLE_LINE_HEIGHT);
            $y -= $height;
            $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 0.3, '0.85 0.85 0.85');

            foreach ($this->accessories?->findByArticle($contentEntry->article->id) ?? [] as $accessory) {
                $labelWidth = $writer->textWidth('Zubehör', self::TABLE_FONT_SIZE - 1, true) + 8;
                $textX = $columns['article'] + self::ACCESSORY_INDENT + $labelWidth;
                $lines = $wrap($accessory->description, $pageWidth - self::MARGIN - $textX, self::TABLE_FONT_SIZE);
                $height = count($lines) * self::TABLE_LINE_HEIGHT + self::ROW_PADDING;
                if ($y - $height < self::MARGIN + 20) {
                    $y = $newPage();
                    $y = $this->drawTableHeader($writer, $y, $pageWidth, $columns);
                }

                $writer->filledRect(self::MARGIN, $y - $height, $pageWidth - 2 * self::MARGIN, $height, self::ACCESSORY_FILL);
                $baseline = $y - self::ROW_PADDING / 2 - self::TABLE_LINE_HEIGHT + 2;
                $this->drawCheckbox($writer, $columns['check'] + 4, $baseline - 1);
                $writer->textRight($columns['quantity'] + self::QUANTITY_COLUMN_WIDTH - 6, $baseline, ($accessory->quantity * $contentEntry->item->quantity) . ' ×', self::TABLE_FONT_SIZE, false, color: self::ACCESSORY_LABEL_COLOR);
                $writer->text($columns['article'] + self::ACCESSORY_INDENT, $baseline, 'Zubehör', self::TABLE_FONT_SIZE - 1, true, false, self::ACCESSORY_LABEL_COLOR);
                foreach ($lines as $i => $line) {
                    $writer->text($textX, $baseline - $i * self::TABLE_LINE_HEIGHT, $line, self::TABLE_FONT_SIZE, false, false, '0.2 0.2 0.2');
                }
                $y -= $height;
                $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 0.3, '0.85 0.85 0.85');
            }
        }
        $writer->setFontRole($previousFontRole);

        // --- Hinweis --------------------------------------------------------
        $blocks = $this->richTextRenderer->parse($entry->inventoryNumber->noticeHtml);
        if ($blocks !== []) {
            $y -= 18;
            if ($y - 60 < self::MARGIN + 20) {
                $y = $newPage();
            }
            $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y - 12, 'Hinweise', 12, true);
            $y -= 30;
            $y = $this->richTextRenderer->render($writer, $blocks, self::MARGIN, $y, $contentWidth, self::MARGIN + 20, $newPage);
        }

        // --- Fotos ----------------------------------------------------------
        $photos = array_values(array_filter(array_map(fn ($image) => $this->preparePhoto($image->filePath), $entry->images)));
        if ($photos !== []) {
            $y -= 18;
            $columnWidth = ($contentWidth - self::PHOTO_GAP) / 2;
            // Ein einzelnes Foto darf die volle Breite nutzen.
            $single = count($photos) === 1;
            $boxWidth = $single ? $contentWidth : $columnWidth;

            $firstHeight = $this->fit($photos[0], $boxWidth, self::PHOTO_MAX_HEIGHT)[1];
            if ($y - 22 - $firstHeight < self::MARGIN + 20) {
                $y = $newPage();
            }
            $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y - 12, 'So wird das Case gepackt', 12, true);
            $y -= 24;

            for ($i = 0; $i < count($photos); $i += $single ? 1 : 2) {
                $pair = $single ? [$photos[$i]] : array_slice($photos, $i, 2);
                $sizes = array_map(fn ($photo) => $this->fit($photo, $boxWidth, self::PHOTO_MAX_HEIGHT), $pair);
                $rowHeight = max(array_map(static fn ($size) => $size[1], $sizes));
                if ($y - $rowHeight < self::MARGIN + 20) {
                    $y = $newPage();
                }
                foreach ($pair as $k => $photo) {
                    [$width, $height] = $sizes[$k];
                    $boxX = self::MARGIN + $k * ($columnWidth + self::PHOTO_GAP);
                    $writer->image($photo, $boxX + ($boxWidth - $width) / 2, $y - $height, $width, $height);
                }
                $y -= $rowHeight + self::PHOTO_GAP;
            }
        }

        return $writer->output();
    }

    private function drawHeader(SimplePdfWriter $writer, float $pageWidth, float $pageHeight, int $pageNumber, CaseItemDetail $detail, CaseInventoryNumberWithContent $entry, ?string $logoJpeg): float
    {
        $y = $pageHeight - self::MARGIN;
        $logoBottomY = null;
        if ($logoJpeg !== null) {
            [$logoWidth, $logoHeight] = $this->fit($logoJpeg, self::LOGO_MAX_WIDTH, self::LOGO_MAX_HEIGHT);
            $writer->image($logoJpeg, $pageWidth - self::MARGIN - $logoWidth, $y - $logoHeight, $logoWidth, $logoHeight);
            $logoBottomY = $y - $logoHeight;
        }

        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y - 4, 'Stückliste', 18, true);
        $y -= 26;
        $writer->text(self::MARGIN, $y, $detail->case->name, 11, true);
        $y -= 14;
        $writer->text(self::MARGIN, $y, ($entry->inventoryNumber->caseNumber !== null ? 'Case-Nr.: ' . $entry->inventoryNumber->caseNumber . ' · ' : '') . 'Inventurnummer: ' . $entry->inventoryNumber->number . ' · Hersteller: ' . $detail->manufacturer->name, 9, false, false, '0.3 0.3 0.3');
        $y -= 16;

        if ($logoBottomY !== null) {
            $y = min($y, $logoBottomY - 10);
        }

        $writer->textRight($pageWidth - self::MARGIN, $y, 'Seite ' . $pageNumber, 8);
        $y -= 6;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, '0.4 0.4 0.4');

        return $y - 16;
    }

    private function drawContinuationHeader(SimplePdfWriter $writer, float $pageWidth, float $pageHeight, int $pageNumber, string $subtitle): float
    {
        $y = $pageHeight - self::MARGIN;
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, self::MARGIN, $y - 4, 'Stückliste (Fortsetzung)', 14, true);
        $y -= 22;
        $writer->text(self::MARGIN, $y, $subtitle, 9, false, false, '0.3 0.3 0.3');
        $writer->textRight($pageWidth - self::MARGIN, $y, 'Seite ' . $pageNumber, 8);
        $y -= 8;
        $writer->line(self::MARGIN, $y, $pageWidth - self::MARGIN, $y, 1.0, '0.4 0.4 0.4');

        return $y - 16;
    }

    /** @param array{check: float, quantity: float, article: float} $columns */
    private function drawTableHeader(SimplePdfWriter $writer, float $y, float $pageWidth, array $columns): float
    {
        $previousFontRole = $writer->setFontRole(SimplePdfWriter::ROLE_TABLE);
        try {
            $writer->filledRect(self::MARGIN, $y - 14, $pageWidth - 2 * self::MARGIN, 18, '0.92 0.92 0.92');
            $writer->textRight($columns['quantity'] + self::QUANTITY_COLUMN_WIDTH - 6, $y - 8, 'Menge', 9, true);
            $writer->text($columns['article'], $y - 8, 'Artikel', 9, true);

            return $y - 20;
        } finally {
            $writer->setFontRole($previousFontRole);
        }
    }

    private function drawCheckbox(SimplePdfWriter $writer, float $x, float $y): void
    {
        $size = self::CHECKBOX_SIZE;
        $color = '0.35 0.35 0.35';
        $writer->line($x, $y, $x + $size, $y, 0.7, $color);
        $writer->line($x + $size, $y, $x + $size, $y + $size, 0.7, $color);
        $writer->line($x + $size, $y + $size, $x, $y + $size, 0.7, $color);
        $writer->line($x, $y + $size, $x, $y, 0.7, $color);
    }

    /** Foto als JPEG (höchstens PHOTO_PIXELS Kantenlänge, Transparenz auf Weiß). */
    private function preparePhoto(string $filePath): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $absolutePath = $this->fileStorage->absolutePath('cases', $filePath);
        if (!is_file($absolutePath)) {
            return null;
        }

        return $this->toJpeg((string) @file_get_contents($absolutePath), self::PHOTO_PIXELS);
    }

    private function toJpeg(string $data, ?int $maxPixels): ?string
    {
        if ($data === '') {
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

        $scale = $maxPixels !== null ? min(1.0, $maxPixels / max($width, $height)) : 1.0;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        return $jpeg === false || $jpeg === '' ? null : $jpeg;
    }

    /** Siehe OfferPdfGenerator::prepareLogoJpeg() — bewusst dupliziert (Projektkonvention je PDF-Generator). */
    private function prepareLogoJpeg(CompanySettings $companySettings): ?string
    {
        if ($companySettings->logoFilename === null || !extension_loaded('gd')) {
            return null;
        }
        $absolutePath = $this->fileStorage->absolutePath('company', $companySettings->logoFilename);
        if (!is_file($absolutePath)) {
            return null;
        }

        return $this->toJpeg((string) @file_get_contents($absolutePath), null);
    }

    /** @return array{0: float, 1: float} in die Box eingepasste Breite/Höhe in pt */
    private function fit(string $jpegBytes, float $maxWidth, float $maxHeight): array
    {
        $size = @getimagesizefromstring($jpegBytes);
        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return [$maxWidth, $maxHeight];
        }
        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return [$size[0] * $scale, $size[1] * $scale];
    }
}
