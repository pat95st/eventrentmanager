<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use RuntimeException;

/**
 * Minimaler, von Hand geschriebener PDF-Generator (roher PDF-1.4-Syntax:
 * Objekte, xref-Tabelle, Streams) — ohne externe Bibliothek, da im Sandbox-
 * Betrieb keine Composer-Pakete nachinstalliert werden können.
 *
 * Unterstützt genau das, was für einfache Dokumente (Text, Linien,
 * Rechtecke, ein eingebettetes JPEG) benötigt wird. Kennt nichts über den
 * fachlichen Inhalt eines konkreten Dokuments (siehe dafür
 * PackingListPdfGenerator) — reine Low-Level-Schreib-Mechanik, damit sie für
 * spätere weitere PDF-Dokumente wiederverwendet werden kann.
 */
final class SimplePdfWriter
{
    private const FONT_REGULAR = 'F1';
    private const FONT_BOLD = 'F2';
    private const FONT_ITALIC = 'F3';
    private const FONT_BOLD_ITALIC = 'F4';

    /** @var array<int, string> Fertig serialisierter Objekt-Body (ohne "N 0 obj"/"endobj"), indiziert nach Objektnummer. */
    private array $objects = [];

    private int $nextObjectNumber = 1;

    /** @var int[] Objektnummern der Seiten, in Reihenfolge. */
    private array $pageObjectNumbers = [];

    /** @var array<int, string> Content-Stream je Seite (Index = Position in pageObjectNumbers). */
    private array $pageContents = [];

    /** @var array<int, array<string, int>> XObject-Ressourcen (Name => Objektnummer) je Seite. */
    private array $pageXObjects = [];

    private float $pageWidth;

    private float $pageHeight;

    private int $currentPageIndex = -1;

    private int $nextImageNameSuffix = 1;

    /** @var array<string, int> md5 der JPEG-Bytes => Objektnummer */
    private array $imageObjectsByHash = [];

    public function __construct(float $pageWidth = 595.28, float $pageHeight = 841.89)
    {
        $this->pageWidth = $pageWidth;
        $this->pageHeight = $pageHeight;
    }

    public function pageWidth(): float
    {
        return $this->pageWidth;
    }

    public function pageHeight(): float
    {
        return $this->pageHeight;
    }

    public function pageCount(): int
    {
        return count($this->pageObjectNumbers);
    }

    /**
     * Macht eine bereits angelegte Seite (0-basiert) wieder zur aktuellen
     * Zeichenfläche — z. B. um nach dem Erzeugen aller Seiten auf jeder Seite
     * die Fußzeile zu zeichnen (siehe DocumentFooter).
     */
    public function selectPage(int $index): void
    {
        if ($index < 0 || $index >= count($this->pageObjectNumbers)) {
            throw new RuntimeException("Seite {$index} existiert nicht.");
        }
        $this->currentPageIndex = $index;
    }

    /** Beginnt eine neue Seite und macht sie zur aktuellen Zeichenfläche. */
    public function addPage(): void
    {
        $this->pageObjectNumbers[] = -1; // Platzhalter, echte Objektnummer erst beim Bauen vergeben
        $this->currentPageIndex = count($this->pageObjectNumbers) - 1;
        $this->pageContents[$this->currentPageIndex] = '';
        $this->pageXObjects[$this->currentPageIndex] = [];
    }

    /**
     * Schreibt einen Text an Position (x, y), Ursprung unten links, wie in
     * PDF üblich. $italic nutzt eine der vier Standard-14-PDF-Schriftarten
     * (Helvetica-Oblique bzw. Helvetica-BoldOblique bei bold+italic
     * zusammen) — genau wie Helvetica/Helvetica-Bold garantiert jeder
     * PDF-Betrachter diese mit, ohne dass eine Schriftdatei eingebettet
     * werden müsste (siehe App\Infrastructure\Pdf\RichTextPdfRenderer, der
     * $italic für <em>/<i> aus dem Rich-Text-Editor braucht).
     *
     * $color (RGB-String "r g b", je 0.0-1.0, wie bei line()/filledRect())
     * ist optional und defaultet auf Schwarz, sodass bestehende Aufrufer ihr
     * Ausgabeverhalten dadurch nicht ändern (siehe App\Domain\Settings\PdfDesignSettings,
     * die diesen Parameter für die Titel-Zeile nutzt).
     */
    public function text(float $x, float $y, string $text, float $fontSize = 10.0, bool $bold = false, bool $italic = false, string $color = '0 0 0'): void
    {
        $this->assertHasPage();
        $font = $this->fontNameFor($bold, $italic);
        $escaped = $this->escapeString($text);
        $this->appendToCurrentPage(sprintf(
            "q %s rg BT /%s %s Tf %s %s Td (%s) Tj ET Q\n",
            $color,
            $font,
            $this->num($fontSize),
            $this->num($x),
            $this->num($y),
            $escaped
        ));
    }

    /**
     * Wie text(), aber rechtsbündig: $xRight ist die rechte Kante des
     * Textes (z. B. für Preisspalten und Summenzeilen, damit Beträge
     * untereinander an der Dezimalstelle bzw. "EUR" ausgerichtet sind).
     */
    public function textRight(float $xRight, float $y, string $text, float $fontSize = 10.0, bool $bold = false, bool $italic = false, string $color = '0 0 0'): void
    {
        $this->text($xRight - $this->textWidth($text, $fontSize, $bold), $y, $text, $fontSize, $bold, $italic, $color);
    }

    /**
     * Breite eines Textes in Punkt anhand der Standard-Zeichenbreiten
     * (AFM-Metriken) von Helvetica bzw. Helvetica-Bold — die beiden
     * Standard-14-Schriften, die dieser Writer verwendet (kursiv hat
     * dieselben Breiten wie normal). Zeichen außerhalb von ASCII (Umlaute,
     * €, Gedankenstrich …) werden mit einer typischen Breite angenähert;
     * für die Rechtsbündigkeit von Beträgen ("1.234,56 EUR") ist das exakt.
     */
    public function textWidth(string $text, float $fontSize, bool $bold = false): float
    {
        $widths = $bold ? self::HELVETICA_BOLD_WIDTHS : self::HELVETICA_WIDTHS;
        $units = 0;
        foreach (mb_str_split($text) as $char) {
            $code = ord($char);
            if (strlen($char) === 1 && $code >= 32 && $code <= 126) {
                $units += $widths[$code - 32];
            } elseif (in_array($char, ['Ä', 'Ö', 'Ü'], true)) {
                $units += $bold ? 722 : 667;
            } else {
                $units += $bold ? 611 : 556;
            }
        }

        return $units * $fontSize / 1000;
    }

    /** Helvetica-Zeichenbreiten (1/1000 em) für ASCII 32–126. */
    private const HELVETICA_WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** Helvetica-Bold-Zeichenbreiten (1/1000 em) für ASCII 32–126. */
    private const HELVETICA_BOLD_WIDTHS = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
    ];

    /** Zieht eine Linie von (x1, y1) nach (x2, y2). */
    public function line(float $x1, float $y1, float $x2, float $y2, float $lineWidth = 0.5, string $strokeColor = '0.7 0.7 0.7'): void
    {
        $this->assertHasPage();
        $this->appendToCurrentPage(sprintf(
            "q %s w %s RG %s %s m %s %s l S Q\n",
            $this->num($lineWidth),
            $strokeColor,
            $this->num($x1),
            $this->num($y1),
            $this->num($x2),
            $this->num($y2)
        ));
    }

    /**
     * Zeichnet einen leeren (nicht gefüllten) Kreis mit Mittelpunkt (x, y)
     * und Radius radius — z. B. als handschriftlich abhakbares Kästchen auf
     * der Packliste (siehe PackingListPdfGenerator). PDF-Content-Streams
     * kennen keinen nativen Kreis-Operator, daher die übliche Annäherung
     * über vier kubische Bézier-Segmente mit der "magischen" Kappa-Konstante
     * (kappa ≈ 0.5523), die einen Vollkreis visuell nicht von einem
     * echten Kreis unterscheidbar macht.
     */
    public function drawCircle(float $x, float $y, float $radius, float $lineWidth = 0.8, string $strokeColor = '0.3 0.3 0.3'): void
    {
        $this->assertHasPage();

        $k = $radius * 0.5522847498;

        $top = [$x, $y + $radius];
        $right = [$x + $radius, $y];
        $bottom = [$x, $y - $radius];
        $left = [$x - $radius, $y];

        $chunk = sprintf(
            "q %s w %s RG %s %s m " .
            "%s %s %s %s %s %s c " .
            "%s %s %s %s %s %s c " .
            "%s %s %s %s %s %s c " .
            "%s %s %s %s %s %s c " .
            "S Q\n",
            $this->num($lineWidth),
            $strokeColor,
            $this->num($top[0]), $this->num($top[1]),
            // top -> right
            $this->num($top[0] + $k), $this->num($top[1]), $this->num($right[0]), $this->num($right[1] + $k), $this->num($right[0]), $this->num($right[1]),
            // right -> bottom
            $this->num($right[0]), $this->num($right[1] - $k), $this->num($bottom[0] + $k), $this->num($bottom[1]), $this->num($bottom[0]), $this->num($bottom[1]),
            // bottom -> left
            $this->num($bottom[0] - $k), $this->num($bottom[1]), $this->num($left[0]), $this->num($left[1] - $k), $this->num($left[0]), $this->num($left[1]),
            // left -> top
            $this->num($left[0]), $this->num($left[1] + $k), $this->num($top[0] - $k), $this->num($top[1]), $this->num($top[0]), $this->num($top[1])
        );

        $this->appendToCurrentPage($chunk);
    }

    /** Zeichnet ein gefülltes Rechteck (Ursprung der Box unten links). */
    public function filledRect(float $x, float $y, float $width, float $height, string $fillColor = '0.95 0.95 0.95'): void
    {
        $this->assertHasPage();
        $this->appendToCurrentPage(sprintf(
            "q %s rg %s %s %s %s re f Q\n",
            $fillColor,
            $this->num($x),
            $this->num($y),
            $this->num($width),
            $this->num($height)
        ));
    }

    /**
     * Bettet ein JPEG-Bild (rohe DCTDecode-Bytes) ein und platziert es in der
     * Box (x, y, width, height); (x, y) ist die untere linke Ecke.
     */
    public function image(string $jpegBytes, float $x, float $y, float $width, float $height): void
    {
        $this->assertHasPage();

        $size = @getimagesizefromstring($jpegBytes);
        if ($size === false || $size[2] !== IMAGETYPE_JPEG) {
            throw new RuntimeException('Nur JPEG-Bilddaten können eingebettet werden.');
        }

        // Gleiche Bilddaten (z. B. dasselbe Artikelbild in mehreren
        // Positionen) nur einmal einbetten und das XObject wiederverwenden.
        $hash = md5($jpegBytes);
        if (isset($this->imageObjectsByHash[$hash])) {
            $this->placeImageObject($this->imageObjectsByHash[$hash], $x, $y, $width, $height);

            return;
        }

        $imageObjectNumber = $this->nextObjectNumber++;
        $this->imageObjectsByHash[$hash] = $imageObjectNumber;
        $channels = $size['channels'] ?? 3;
        $colorSpace = $channels === 1 ? '/DeviceGray' : '/DeviceRGB';

        $dict = sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>",
            $size[0],
            $size[1],
            $colorSpace,
            strlen($jpegBytes)
        );
        $this->objects[$imageObjectNumber] = $dict . "\nstream\n" . $jpegBytes . "\nendstream";

        $this->placeImageObject($imageObjectNumber, $x, $y, $width, $height);
    }

    private function placeImageObject(int $imageObjectNumber, float $x, float $y, float $width, float $height): void
    {
        $imageName = 'Im' . ($this->nextImageNameSuffix++);
        $this->pageXObjects[$this->currentPageIndex][$imageName] = $imageObjectNumber;

        $this->appendToCurrentPage(sprintf(
            "q %s 0 0 %s %s %s cm /%s Do Q\n",
            $this->num($width),
            $this->num($height),
            $this->num($x),
            $this->num($y),
            $imageName
        ));
    }

    /** Baut das fertige PDF-Dokument (roher PDF-1.4-Binärstring) und liefert es zurück. */
    public function output(): string
    {
        if ($this->pageObjectNumbers === []) {
            throw new RuntimeException('PDF ohne Seiten kann nicht erzeugt werden.');
        }

        // Objektnummern für Font, Pages-Wurzel, Catalog und jede Seite/deren
        // Content-Stream final vergeben (Bild-Objekte haben ihre Nummer
        // bereits beim Einbetten erhalten).
        $fontRegularObjectNumber = $this->nextObjectNumber++;
        $fontBoldObjectNumber = $this->nextObjectNumber++;
        $fontItalicObjectNumber = $this->nextObjectNumber++;
        $fontBoldItalicObjectNumber = $this->nextObjectNumber++;
        $pagesObjectNumber = $this->nextObjectNumber++;
        $catalogObjectNumber = $this->nextObjectNumber++;

        $this->objects[$fontRegularObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $this->objects[$fontBoldObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $this->objects[$fontItalicObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique /Encoding /WinAnsiEncoding >>';
        $this->objects[$fontBoldItalicObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique /Encoding /WinAnsiEncoding >>';

        $pageContentObjectNumbers = [];
        foreach ($this->pageObjectNumbers as $index => $_) {
            $contentObjectNumber = $this->nextObjectNumber++;
            $stream = $this->pageContents[$index];
            $this->objects[$contentObjectNumber] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream);
            $pageContentObjectNumbers[$index] = $contentObjectNumber;

            $pageObjectNumber = $this->nextObjectNumber++;
            $this->pageObjectNumbers[$index] = $pageObjectNumber;
        }

        foreach ($this->pageObjectNumbers as $index => $pageObjectNumber) {
            $resourceFonts = sprintf(
                '/Font << /%s %d 0 R /%s %d 0 R /%s %d 0 R /%s %d 0 R >>',
                self::FONT_REGULAR,
                $fontRegularObjectNumber,
                self::FONT_BOLD,
                $fontBoldObjectNumber,
                self::FONT_ITALIC,
                $fontItalicObjectNumber,
                self::FONT_BOLD_ITALIC,
                $fontBoldItalicObjectNumber
            );

            $xObjectEntries = '';
            foreach ($this->pageXObjects[$index] as $name => $objNum) {
                $xObjectEntries .= "/{$name} {$objNum} 0 R ";
            }
            $resourceXObjects = $xObjectEntries !== '' ? sprintf('/XObject << %s>>', $xObjectEntries) : '';

            $this->objects[$pageObjectNumber] = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources << %s %s >> /Contents %d 0 R >>",
                $pagesObjectNumber,
                $this->num($this->pageWidth),
                $this->num($this->pageHeight),
                $resourceFonts,
                $resourceXObjects,
                $pageContentObjectNumbers[$index]
            );
        }

        $kids = implode(' ', array_map(fn (int $n) => "{$n} 0 R", $this->pageObjectNumbers));
        $this->objects[$pagesObjectNumber] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            $kids,
            count($this->pageObjectNumbers)
        );

        $this->objects[$catalogObjectNumber] = sprintf('<< /Type /Catalog /Pages %d 0 R >>', $pagesObjectNumber);

        return $this->serialize($catalogObjectNumber);
    }

    /** @return string Vollständiges PDF-Binärdokument inkl. Header, Objekten, xref-Tabelle und Trailer. */
    private function serialize(int $catalogObjectNumber): string
    {
        $highestObjectNumber = max(array_keys($this->objects));

        $output = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        for ($n = 1; $n <= $highestObjectNumber; $n++) {
            if (!isset($this->objects[$n])) {
                $offsets[$n] = null;

                continue;
            }

            $offsets[$n] = strlen($output);
            $output .= "{$n} 0 obj\n" . $this->objects[$n] . "\nendobj\n";
        }

        $xrefOffset = strlen($output);

        $output .= "xref\n0 " . ($highestObjectNumber + 1) . "\n";
        $output .= "0000000000 65535 f \n";
        for ($n = 1; $n <= $highestObjectNumber; $n++) {
            if ($offsets[$n] === null) {
                $output .= "0000000000 00000 f \n";

                continue;
            }
            $output .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }

        $output .= "trailer\n";
        $output .= sprintf("<< /Size %d /Root %d 0 R >>\n", $highestObjectNumber + 1, $catalogObjectNumber);
        $output .= "startxref\n{$xrefOffset}\n%%EOF";

        return $output;
    }

    private function fontNameFor(bool $bold, bool $italic): string
    {
        if ($bold && $italic) {
            return self::FONT_BOLD_ITALIC;
        }
        if ($bold) {
            return self::FONT_BOLD;
        }
        if ($italic) {
            return self::FONT_ITALIC;
        }

        return self::FONT_REGULAR;
    }

    private function appendToCurrentPage(string $chunk): void
    {
        $this->pageContents[$this->currentPageIndex] .= $chunk;
    }

    private function assertHasPage(): void
    {
        if ($this->currentPageIndex === -1) {
            throw new RuntimeException('Es wurde noch keine Seite begonnen (addPage() aufrufen).');
        }
    }

    /** Escaped einen String für die Verwendung als PDF-Literal-String "(...)". */
    private function escapeString(string $text): string
    {
        // In WinAnsiEncoding (cp1252) konvertieren, damit Umlaute etc. mit
        // dem Standard-Helvetica-Zeichensatz korrekt dargestellt werden.
        $converted = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if ($converted === false) {
            $converted = $text;
        }

        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $converted);

        return str_replace(["\r", "\n"], ['', ' '], $escaped);
    }

    private function num(float $value): string
    {
        $rounded = round($value, 3);
        $formatted = rtrim(rtrim(sprintf('%.3F', $rounded), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
