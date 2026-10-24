<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

use RuntimeException;

/**
 * Treiber für Brother P-touch-Etikettendrucker (getestetes Zielgerät:
 * PT-E550W), die im "Raster Mode" über eine rohe TCP-Verbindung auf Port
 * 9100 angesteuert werden — demselben Befehlssatz, den auch die
 * QL-Etikettendrucker-Serie verwendet ("ESC/P Raster Mode", öffentlich als
 * Brothers "Raster Command Reference" dokumentiert).
 *
 * ACHTUNG (siehe auch Abschlussbericht der Implementierung): Diese
 * Implementierung wurde ausschließlich anhand öffentlich zugänglicher
 * Beschreibungen des Brother-Rasterprotokolls erstellt. In dieser
 * Sandbox-Umgebung steht KEINE physische Brother-P-touch-Hardware zur
 * Verfügung, die Byte-Sequenzen konnten daher nicht gegen ein echtes Gerät
 * verifiziert werden. Vor produktivem Einsatz unbedingt mit einem echten
 * Testdruck prüfen (siehe "Testdruck"-Button in den Drucker-Einstellungen)
 * und bei Abweichungen die unten zentral kommentierten Annahmen (DPI,
 * Bytebreite je Bandbreite, Aufbau des "Print Information"-Kommandos)
 * anpassen.
 */
final class BrotherPtouchRasterDriver implements LabelPrinterDriverInterface
{
    // --- Zentrale Annahmen zum Rasterprotokoll -----------------------------
    //
    // Auflösung: Brother-Etikettendrucker (P-touch/QL-Serie) drucken laut
    // öffentlicher Dokumentation mit 180 dpi in beiden Achsen.
    private const DPI = 180;

    // Gesamtbreite des Druckkopfs in Pixeln/Bits je Rasterzeile. Bei den
    // "großen" Brother-Modellen (QL-570/580N/700/800, zu denen die
    // PT-E550W-Rasterengine baugleich ist) sind das dokumentiert 720 Punkte
    // = 90 Byte je Zeile, unabhängig von der eingelegten Bandbreite — die
    // Bilddaten für schmalere Bänder werden innerhalb dieser 90 Byte
    // zentriert übertragen (siehe self::rowToPackedBytes()). ANNAHME, falls
    // das reale Gerät einen anderen Druckkopf hat, muss dieser Wert
    // angepasst werden.
    private const FULL_HEAD_DOTS = 720;
    private const FULL_HEAD_BYTES = self::FULL_HEAD_DOTS / 8; // 90

    /**
     * Zuordnung Bandbreite (mm) -> tatsächlich bedruckbare Breite in Punkten
     * (Pixel quer zur Laufrichtung). Werte gemäß den in der Aufgabenstellung
     * recherchierten/üblichen Richtwerten für P-touch/QL-Modelle dieser
     * Baureihe (12 mm ≈ 70 Punkte, 24 mm ≈ 128 Punkte, 29 mm ≈ 164 Punkte).
     * Zentrale Stelle für Korrekturen nach einem echten Testdruck.
     *
     * @var array<int, int>
     */
    private const TAPE_WIDTH_PRINT_DOTS = [
        12 => 70,
        24 => 128,
        29 => 164,
    ];

    // Modulbreite des Code128-Barcodes in Pixeln (bei 180 dpi ≈ 0,28 mm je
    // Modul) — schmal genug, um auf 12-mm-Band zu passen, aber laut
    // Code-128-Spezifikation (min. 2 Pixel je Modul üblich) noch mit
    // handelsüblichen Scannern lesbar. ANNAHME, ggf. nach echtem Testdruck
    // vergrößern, falls der Scanner Probleme hat.
    private const BARCODE_MODULE_WIDTH_PX = 2;

    // Ruhezone (Quiet Zone) links/rechts des Barcodes in Modulen, gemäß
    // Code-128-Spezifikation (>= 10 Module empfohlen).
    private const BARCODE_QUIET_ZONE_MODULES = 10;

    private readonly Code128Encoder $code128;

    public function __construct(private readonly TcpPrinterTransport $transport)
    {
        $this->code128 = new Code128Encoder();
    }

    public function getSupportedTapeWidths(): array
    {
        return array_keys(self::TAPE_WIDTH_PRINT_DOTS);
    }

    public function renderLabel(LabelContent $content, PrinterProfile $profile): string
    {
        $tapeWidthDots = self::TAPE_WIDTH_PRINT_DOTS[$profile->tapeWidthMm]
            ?? throw new RuntimeException("Nicht unterstützte Bandbreite: {$profile->tapeWidthMm} mm.");

        $image = $this->buildLabelImage($content, $tapeWidthDots);

        try {
            return $this->imageToRasterCommands($image, $tapeWidthDots, $profile);
        } finally {
            imagedestroy($image);
        }
    }

    public function sendTestPrint(PrinterProfile $profile): void
    {
        $content = new LabelContent(
            barcode: null,
            lines: [
                $profile->name,
                (new \DateTimeImmutable())->format('d.m.Y H:i'),
            ],
        );

        $bytes = $this->renderLabel($content, $profile);
        $this->transport->send($profile->ipAddress, $profile->port, $bytes);
    }

    /**
     * Baut das Etikett als monochromes GD-Bild. Koordinatensystem bewusst so
     * gewählt, dass die Bild-BREITE der Laufrichtung des Bandes entspricht
     * (wird später Zeile für Zeile als Rasterzeile gesendet) und die
     * Bild-HÖHE der Bandbreite quer zur Laufrichtung (wird je Rasterzeile zu
     * Bits gepackt) — siehe imageToRasterCommands().
     */
    private function buildLabelImage(LabelContent $content, int $tapeWidthDots): \GdImage
    {
        $marginPx = 4;
        $barcodeWidths = [];
        $barcodeLengthPx = 0;

        if ($content->barcode !== null) {
            if (($content->barcode['type'] ?? null) !== 'code128') {
                throw new RuntimeException("BrotherPtouchRasterDriver unterstützt aktuell nur den Barcode-Typ \"code128\", erhalten: " . ($content->barcode['type'] ?? '(keiner)'));
            }

            $barcodeWidths = $this->code128->encodeToModuleWidths($content->barcode['value']);
            $moduleCount = array_sum($barcodeWidths) + (2 * self::BARCODE_QUIET_ZONE_MODULES);
            $barcodeLengthPx = $moduleCount * self::BARCODE_MODULE_WIDTH_PX;
        }

        // Schriftgröße an die verfügbare Bandbreite anpassen: auf sehr
        // schmalem Band (12 mm) reicht kein Platz für die große GD-Systemschrift.
        $font = $tapeWidthDots >= 128 ? 5 : 3;
        $textLineHeight = imagefontheight($font) + 2;
        $textAreaHeight = $content->lines === [] ? 0 : (count($content->lines) * $textLineHeight);

        $barcodeAreaHeight = $content->barcode !== null ? max(20, $tapeWidthDots - $textAreaHeight - (3 * $marginPx)) : 0;

        $lengthPx = max($barcodeLengthPx, $this->longestLineWidthPx($content->lines, $font)) + (2 * $marginPx);
        $lengthPx = max($lengthPx, 40); // Mindestlänge, falls Inhalt (theoretisch) leer wäre.

        $image = imagecreate($lengthPx, $tapeWidthDots);
        if ($image === false) {
            throw new RuntimeException('Etikettenbild konnte nicht erzeugt werden (GD imagecreate fehlgeschlagen).');
        }

        // Bei imagecreate() wird die zuerst zugewiesene Farbe automatisch
        // Hintergrundfarbe des gesamten Bildes.
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        $y = $marginPx;

        if ($content->barcode !== null) {
            $x = (int) (($lengthPx - $barcodeLengthPx) / 2) + (self::BARCODE_QUIET_ZONE_MODULES * self::BARCODE_MODULE_WIDTH_PX);
            $isBar = true; // Code128 beginnt immer mit einem Strich.
            foreach ($barcodeWidths as $moduleWidth) {
                $widthPx = $moduleWidth * self::BARCODE_MODULE_WIDTH_PX;
                if ($isBar) {
                    imagefilledrectangle($image, $x, $y, $x + $widthPx - 1, $y + $barcodeAreaHeight - 1, $black);
                }
                $x += $widthPx;
                $isBar = !$isBar;
            }
            $y += $barcodeAreaHeight + $marginPx;
        }

        foreach ($content->lines as $line) {
            $textWidthPx = imagefontwidth($font) * strlen($line);
            $textX = max($marginPx, (int) (($lengthPx - $textWidthPx) / 2));
            imagestring($image, $font, $textX, $y, $line, $black);
            $y += $textLineHeight;
        }

        return $image;
    }

    /** @param string[] $lines */
    private function longestLineWidthPx(array $lines, int $font): int
    {
        $longest = 0;
        foreach ($lines as $line) {
            $longest = max($longest, imagefontwidth($font) * strlen($line));
        }

        return $longest;
    }

    /**
     * Wandelt das GD-Bild in den vollständigen Brother-Raster-Byte-Strom um.
     * Aufbau gemäß öffentlich dokumentiertem Brother-Raster-Befehlssatz
     * (siehe Klassen-Kommentar oben zur Nicht-Verifizierung an echter Hardware):
     *
     *  a) Invalidate: 100x 0x00, dann Initialize (ESC @ = 0x1B 0x40)
     *  b) Rasterbetrieb aktivieren: ESC i a 01 (0x1B 0x69 0x61 0x01)
     *  c) Print-Information-Kommando (ESC i z ...): Medientyp/-breite setzen
     *  d) je Rasterzeile: 'G' (0x47) + 2 Byte Länge (little-endian) + gepackte Bilddaten
     *  e) Abschluss: 0x1A (Drucken mit Vorschub + Auswurf, letzte Seite)
     */
    private function imageToRasterCommands(\GdImage $image, int $tapeWidthDots, PrinterProfile $profile): string
    {
        $lengthPx = imagesx($image);
        $black = imagecolorallocate($image, 0, 0, 0);

        $stream = str_repeat("\x00", 100);
        $stream .= "\x1B\x40"; // ESC @ — Initialize
        $stream .= "\x1B\x69\x61\x01"; // ESC i a 01 — Rastermodus aktivieren

        $stream .= $this->printInformationCommand($tapeWidthDots, $lengthPx, $profile);

        for ($x = 0; $x < $lengthPx; $x++) {
            $rowBytes = $this->rowToPackedBytes($image, $x, $tapeWidthDots, $black);
            $length = strlen($rowBytes);
            $stream .= "\x47" . chr($length & 0xFF) . chr(($length >> 8) & 0xFF) . $rowBytes;
        }

        $stream .= "\x1A"; // Print mit Vorschub + Auswurf (letzte/einzige Seite)

        return $stream;
    }

    /**
     * "Print Information"-Kommando (ESC i z). Byte-Layout gemäß öffentlich
     * dokumentiertem Brother-Rasterprotokoll für QL/P-touch-Modelle:
     *   n1: gültige Felder (Bitmaske) — hier Medientyp (0x02) + Breite (0x04) gesetzt
     *   n2: Medientyp (0x0A = Endlos-/Rollenband)
     *   n3: Bandbreite in mm
     *   n4: Bandlänge in mm (0 = Endlosband, nicht vorab abgelängt)
     *   n5-n8: Anzahl Rasterzeilen (little-endian, 4 Byte)
     *   n9: Startseite (0 = erste/einzige Seite)
     *   n10: reserviert (0)
     * ANNAHME, nicht an echter Hardware verifiziert — siehe Klassen-Kommentar.
     */
    private function printInformationCommand(int $tapeWidthDots, int $rasterLineCount, PrinterProfile $profile): string
    {
        $validFlags = 0x02 | 0x04; // Medientyp + Breite gültig
        $mediaType = 0x0A; // Endlosband
        $widthMm = $profile->tapeWidthMm;
        $lengthMm = 0; // Endlosband, keine feste Etikettenlänge

        $rasterCountBytes = pack('V', $rasterLineCount); // little-endian, 4 Byte

        return "\x1B\x69\x7A"
            . chr($validFlags)
            . chr($mediaType)
            . chr($widthMm & 0xFF)
            . chr($lengthMm & 0xFF)
            . $rasterCountBytes
            . "\x00" // Startseite
            . "\x00"; // reserviert
    }

    /**
     * Packt eine Bildspalte (= eine Rasterzeile in Bandlaufrichtung) zu
     * MSB-first-Bits und zentriert sie innerhalb der vollen Druckkopfbreite
     * (self::FULL_HEAD_BYTES Byte) — siehe Kommentar zu FULL_HEAD_DOTS oben.
     */
    private function rowToPackedBytes(\GdImage $image, int $x, int $tapeWidthDots, int $black): string
    {
        $leftPaddingDots = intdiv(self::FULL_HEAD_DOTS - $tapeWidthDots, 2);
        $bits = str_repeat('0', self::FULL_HEAD_DOTS);

        for ($y = 0; $y < $tapeWidthDots; $y++) {
            $color = imagecolorat($image, $x, $y);
            if ($color === $black) {
                $bitIndex = $leftPaddingDots + $y;
                $bits[$bitIndex] = '1';
            }
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byteBits) {
            $bytes .= chr(bindec($byteBits));
        }

        return $bytes;
    }
}
