<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

/**
 * Reiner PHP-Encoder für Code 128, Zeichensatz B (druckbare ASCII-Zeichen
 * 32–127 — für alphanumerische Inventarnummern ausreichend, siehe
 * BrotherPtouchRasterDriver). Es gibt keine Composer-Abhängigkeiten in
 * diesem Projekt, daher die vollständige, eigene Implementierung nach der
 * öffentlich dokumentierten Code-128-Symbologie (ISO/IEC 15417):
 *
 *  - Start-Symbol "Start B" (Wert 104)
 *  - je ein Symbol pro Datenzeichen, Wert = ASCII-Code - 32 (Zeichensatz-B-
 *    Tabelle, Werte 0-95 für die Zeichen " " (32) bis "_"/DEL-Bereich)
 *  - Prüfsymbol (Modulo 103): Summe aus Startwert (104) plus
 *    (Zeichenwert_i * Position_i), Position beginnt bei 1 für das erste
 *    Datenzeichen, Ergebnis modulo 103
 *  - Stop-Symbol (Wert 106)
 *
 * Jedes der 107 möglichen Symbole (Werte 0-106) hat ein festes, 11 Module
 * breites Balkenmuster aus 6 alternierenden Strich-/Lückenbreiten (die
 * Standard-Musterliste), das Stop-Symbol ist mit 13 Modulen (7 Breiten)
 * eine Ausnahme. Die Musterliste unten ist die vollständige Standardtabelle.
 */
final class Code128Encoder
{
    private const START_B = 104;
    private const STOP = 106;

    /**
     * Vollständige Code-128-Musterliste: Index = Symbolwert (0-106), Wert =
     * Strichbreiten-Folge als String aus 6 Ziffern (bzw. 7 beim Stop-Symbol),
     * abwechselnd Strich/Lücke beginnend mit Strich, in Modulen (1-4 breit).
     * Quelle: öffentlich dokumentierte Code-128-Standardtabelle (ISO/IEC 15417).
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /**
     * @return array{symbols: int[], checksum: int} Für Tests/Nachvollziehbarkeit
     *         getrennt von der reinen Bitmustererzeugung, siehe encodeToWidths().
     */
    public function encodeSymbols(string $value): array
    {
        if ($value === '') {
            throw new \InvalidArgumentException('Code128-Wert darf nicht leer sein.');
        }

        $symbols = [self::START_B];
        $checksum = self::START_B;

        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $byte = ord($value[$i]);
            if ($byte < 32 || $byte > 127) {
                throw new \InvalidArgumentException("Zeichen mit Code {$byte} wird von Code128-Zeichensatz B nicht unterstützt (nur druckbare ASCII-Zeichen 32-127).");
            }

            $codeValue = $byte - 32;
            $symbols[] = $codeValue;
            // Positionsgewicht beginnt bei 1 für das erste Datenzeichen (Startsymbol hat Gewicht 1, nicht 0).
            $checksum += $codeValue * ($i + 1);
        }

        $checksumSymbol = $checksum % 103;
        $symbols[] = $checksumSymbol;
        $symbols[] = self::STOP;

        return ['symbols' => $symbols, 'checksum' => $checksumSymbol];
    }

    /**
     * Liefert die vollständige Balken-/Lückenbreitenfolge (in Modulen) für
     * $value als flaches Array von Integers, beginnend und endend mit einem
     * Strich (Quiet-Zone ist NICHT enthalten, muss vom Aufrufer ergänzt werden).
     *
     * @return int[]
     */
    public function encodeToModuleWidths(string $value): array
    {
        $encoded = $this->encodeSymbols($value);
        $widths = [];

        foreach ($encoded['symbols'] as $symbolValue) {
            $pattern = self::PATTERNS[$symbolValue] ?? null;
            if ($pattern === null) {
                throw new \RuntimeException("Kein Strichmuster für Code128-Symbolwert {$symbolValue} hinterlegt.");
            }

            foreach (str_split($pattern) as $digit) {
                $widths[] = (int) $digit;
            }
        }

        return $widths;
    }

    /** Gesamtbreite in Modulen (für die Berechnung der Pixelbreite bei gegebener Modulbreite). */
    public function totalModuleWidth(string $value): int
    {
        return array_sum($this->encodeToModuleWidths($value));
    }
}
