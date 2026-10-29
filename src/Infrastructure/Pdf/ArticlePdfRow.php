<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Article\Article;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Namensspalte einer Artikelposition in Angebot, AB und Rechnung
 * (Migration 109):
 *
 *   [Bild]  Artikelname (ggf. "enthält: …" bei Bundles)
 *           Kurztext klein und kursiv
 *
 * Das Bild ist das in der Artikelgalerie gewählte Dokumentbild
 * (Article::documentImage()), als kleines quadratisches JPEG mit weißem
 * Hintergrund. Ohne Bild beginnt der Text wie bisher an der Spaltenkante.
 *
 * Zeilen-Arrays der Generatoren bekommen dafür die optionalen Schlüssel
 * `shortTextLines` (string[]) und `image` (?string, JPEG-Bytes);
 * contentHeight() und drawNameColumn() berücksichtigen beide.
 */
final class ArticlePdfRow
{
    /** Kantenlänge des Bildes in pt. */
    public const IMAGE_SIZE = 26.0;
    /** Einzug des Textes rechts neben dem Bild in pt. */
    public const IMAGE_GAP = 32.0;
    public const SHORT_TEXT_FONT_SIZE = 7.5;
    public const SHORT_TEXT_LINE_HEIGHT = 9.5;
    private const THUMBNAIL_PIXELS = 160;

    /** @var array<string, string|null> Bild-ID => JPEG-Bytes (null = nicht ladbar) */
    private array $thumbnails = [];

    public function __construct(private readonly LocalFileStorage $fileStorage)
    {
    }

    /**
     * @param list<string|null> $texts Name und ggf. Zusatzzeilen (null wird übersprungen)
     * @param callable(string, float, float): string[] $wrap Umbruchfunktion des Generators (Text, Breite, Schriftgröße)
     * @return array{nameLines: string[], shortTextLines: string[], image: string|null}
     */
    public function nameColumn(?Article $article, array $texts, float $columnWidth, float $fontSize, callable $wrap): array
    {
        $image = $article !== null ? $this->thumbnail($article) : null;
        $textWidth = $image !== null ? $columnWidth - self::IMAGE_GAP : $columnWidth;

        $nameLines = [];
        foreach ($texts as $text) {
            if ($text !== null && $text !== '') {
                $nameLines = array_merge($nameLines, $wrap($text, $textWidth, $fontSize));
            }
        }

        $shortText = $article?->documentShortText !== null ? trim($article->documentShortText) : '';

        return [
            'nameLines' => $nameLines,
            'shortTextLines' => $shortText !== '' ? $wrap($shortText, $textWidth, self::SHORT_TEXT_FONT_SIZE) : [],
            'image' => $image,
        ];
    }

    /** Höhe des Zeileninhalts ohne Zeilenabstand (Padding addiert der Generator). */
    public static function contentHeight(array $row, float $lineHeight): float
    {
        $height = max(count($row['nameLines']), 1) * $lineHeight
            + count($row['shortTextLines'] ?? []) * self::SHORT_TEXT_LINE_HEIGHT;
        if (($row['image'] ?? null) !== null) {
            $height = max($height, self::IMAGE_SIZE + 2);
        }

        return $height;
    }

    /** Zeichnet Bild, Name (inkl. Zusatzzeilen) und Kurztext ab der Zeilenoberkante $topY. */
    public static function drawNameColumn(SimplePdfWriter $writer, float $x, float $topY, array $row, float $fontSize, float $lineHeight, bool $bold = false): void
    {
        $textX = $x;
        if (($row['image'] ?? null) !== null) {
            $writer->image($row['image'], $x, $topY - 1 - self::IMAGE_SIZE, self::IMAGE_SIZE, self::IMAGE_SIZE);
            $textX = $x + self::IMAGE_GAP;
        }

        $baseline = $topY - $lineHeight + 2;
        foreach ($row['nameLines'] as $i => $line) {
            $writer->text($textX, $baseline - $i * $lineHeight, $line, $fontSize, $bold);
        }

        $lastNameBaseline = $baseline - (max(count($row['nameLines']), 1) - 1) * $lineHeight;
        foreach ($row['shortTextLines'] ?? [] as $i => $line) {
            $writer->text($textX, $lastNameBaseline - ($i + 1) * self::SHORT_TEXT_LINE_HEIGHT, $line, self::SHORT_TEXT_FONT_SIZE, false, true, '0.3 0.3 0.3');
        }
    }

    private function thumbnail(Article $article): ?string
    {
        $image = $article->documentImage();
        if ($image === null || !extension_loaded('gd')) {
            return null;
        }
        if (array_key_exists($image->id, $this->thumbnails)) {
            return $this->thumbnails[$image->id];
        }

        return $this->thumbnails[$image->id] = $this->buildThumbnail($this->fileStorage->absolutePath('articles', $image->filePath));
    }

    /** Quadratisches JPEG, Bild eingepasst und zentriert auf weißem Grund. */
    private function buildThumbnail(string $absolutePath): ?string
    {
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

        $size = self::THUMBNAIL_PIXELS;
        $scale = min($size / $width, $size / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($size, $size);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, intdiv($size - $targetWidth, 2), intdiv($size - $targetHeight, 2), 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        return $jpeg === false || $jpeg === '' ? null : $jpeg;
    }
}
