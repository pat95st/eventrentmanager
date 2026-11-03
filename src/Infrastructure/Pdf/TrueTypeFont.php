<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use RuntimeException;

/**
 * Liest eine TrueType-Schriftdatei (.ttf mit glyf-Outlines, wie sie Google
 * Fonts ausliefert) und erzeugt daraus für SimplePdfWriter:
 *
 * - die Zeichenbreiten für die Codes 32–255 in WinAnsiEncoding (cp1252),
 *   so wie der Writer Text ohnehin kodiert,
 * - die Angaben für den PDF-FontDescriptor (Name, BBox, Ascent …),
 * - eine verkleinerte Schriftdatei ("Subset") mit nur den tatsächlich
 *   verwendeten Glyphen. Die Glyph-IDs bleiben dabei erhalten (unbenutzte
 *   Glyphen werden leer), daher passen cmap und hmtx unverändert; entfernt
 *   werden nur die Outlines und die für PDF unnötigen Tabellen.
 *
 * Ohne externe Bibliothek, siehe SimplePdfWriter.
 */
final class TrueTypeFont
{
    /** Tabellen, die in die eingebettete Datei übernommen werden. */
    private const KEEP_TABLES = ['cmap', 'cvt ', 'fpgm', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'name', 'OS/2', 'post', 'prep'];

    /** @var array<string, self> */
    private static array $cache = [];

    /** @var array<string, array{offset: int, length: int}> */
    private array $tables = [];

    private int $unitsPerEm;
    private int $numGlyphs;
    private int $indexToLocFormat;
    /** @var int[] Advance je Glyph-ID in Font-Einheiten */
    private array $advances = [];
    /** @var array<int, int> Unicode => Glyph-ID */
    private array $cmap = [];
    /** @var int[] */
    private array $bbox;
    private int $ascent;
    private int $descent;
    private int $capHeight;
    private float $italicAngle;
    private bool $fixedPitch;
    private string $postScriptName;

    private function __construct(private readonly string $data)
    {
        $this->parse();
    }

    public static function fromFile(string $path): self
    {
        if (!isset(self::$cache[$path])) {
            $data = @file_get_contents($path);
            if ($data === false || $data === '') {
                throw new RuntimeException("Schriftdatei \"{$path}\" konnte nicht gelesen werden.");
            }
            self::$cache[$path] = new self($data);
        }

        return self::$cache[$path];
    }

    public static function fromBytes(string $data): self
    {
        return new self($data);
    }

    /** PostScript-Name ohne Leer- und Sonderzeichen (für /BaseFont). */
    public function postScriptName(): string
    {
        return $this->postScriptName;
    }

    /** Breite eines cp1252-Zeichencodes in 1/1000 em. */
    public function widthOfCode(int $code): int
    {
        $unicode = self::cp1252ToUnicode($code);
        $glyph = $this->cmap[$unicode] ?? 0;

        return $this->advanceOf($glyph);
    }

    /** @return int[] Breiten für die Codes 32–255 in 1/1000 em */
    public function widths(): array
    {
        $widths = [];
        for ($code = 32; $code <= 255; $code++) {
            $widths[] = $this->widthOfCode($code);
        }

        return $widths;
    }

    /** Hat die Schrift für diesen Unicode-Codepoint eine Glyphe? */
    public function hasGlyph(int $unicode): bool
    {
        return ($this->cmap[$unicode] ?? 0) !== 0;
    }

    /** @return array{fontBBox: int[], ascent: int, descent: int, capHeight: int, italicAngle: float, flags: int} in 1/1000 em */
    public function descriptor(): array
    {
        $scale = fn (int $v) => (int) round($v * 1000 / $this->unitsPerEm);
        $flags = 32; // nicht-symbolisch (Standard-Zeichensatz)
        if ($this->fixedPitch) {
            $flags |= 1;
        }
        if ($this->italicAngle !== 0.0) {
            $flags |= 64;
        }

        return [
            'fontBBox' => array_map($scale, $this->bbox),
            'ascent' => $scale($this->ascent),
            'descent' => $scale($this->descent),
            'capHeight' => $scale($this->capHeight),
            'italicAngle' => $this->italicAngle,
            'flags' => $flags,
        ];
    }

    /**
     * Schriftdatei, die nur die Glyphen der angegebenen cp1252-Codes (plus
     * .notdef und Bestandteile zusammengesetzter Glyphen) enthält.
     *
     * @param int[] $codes
     */
    public function subset(array $codes): string
    {
        $keep = [0 => true];
        foreach ($codes as $code) {
            $glyph = $this->cmap[self::cp1252ToUnicode($code)] ?? 0;
            $keep[$glyph] = true;
        }

        $glyfOffset = $this->tables['glyf']['offset'];
        $locations = $this->locations();

        // Bestandteile zusammengesetzter Glyphen ergänzen (auch verschachtelt).
        $queue = array_keys($keep);
        while ($queue !== []) {
            $glyph = array_pop($queue);
            foreach ($this->componentGlyphs($glyfOffset + $locations[$glyph], $locations[$glyph + 1] - $locations[$glyph]) as $component) {
                if (!isset($keep[$component]) && $component < $this->numGlyphs) {
                    $keep[$component] = true;
                    $queue[] = $component;
                }
            }
        }

        $glyf = '';
        $loca = '';
        for ($glyph = 0; $glyph < $this->numGlyphs; $glyph++) {
            $loca .= pack('N', strlen($glyf));
            if (isset($keep[$glyph])) {
                $length = $locations[$glyph + 1] - $locations[$glyph];
                if ($length > 0) {
                    $glyf .= substr($this->data, $glyfOffset + $locations[$glyph], $length);
                    $glyf .= str_repeat("\0", (4 - strlen($glyf) % 4) % 4);
                }
            }
        }
        $loca .= pack('N', strlen($glyf));

        $head = $this->table('head');
        $head = substr_replace($head, "\0\0\0\0", 8, 4);  // checkSumAdjustment, wird unten gesetzt
        $head = substr_replace($head, pack('n', 1), 50, 2); // indexToLocFormat = lang

        $tables = [];
        foreach (self::KEEP_TABLES as $tag) {
            if ($tag === 'glyf') {
                $tables[$tag] = $glyf;
            } elseif ($tag === 'loca') {
                $tables[$tag] = $loca;
            } elseif ($tag === 'head') {
                $tables[$tag] = $head;
            } elseif (isset($this->tables[$tag])) {
                $tables[$tag] = $this->table($tag);
            }
        }

        return $this->buildFont($tables);
    }

    /** @param array<string, string> $tables */
    private function buildFont(array $tables): string
    {
        ksort($tables, SORT_STRING);
        $numTables = count($tables);
        $entrySelector = (int) floor(log($numTables, 2));
        $searchRange = (2 ** $entrySelector) * 16;
        $rangeShift = $numTables * 16 - $searchRange;

        $header = pack('Nnnnn', 0x00010000, $numTables, $searchRange, $entrySelector, $rangeShift);
        $offset = 12 + 16 * $numTables;
        $directory = '';
        $body = '';
        $headOffset = null;
        foreach ($tables as $tag => $content) {
            if ($tag === 'head') {
                $headOffset = $offset;
            }
            $directory .= str_pad($tag, 4) . pack('NNN', self::checksum($content), $offset, strlen($content));
            $padded = $content . str_repeat("\0", (4 - strlen($content) % 4) % 4);
            $body .= $padded;
            $offset += strlen($padded);
        }

        $font = $header . $directory . $body;
        if ($headOffset !== null) {
            $adjustment = (0xB1B0AFBA - self::checksum($font)) & 0xFFFFFFFF;
            $font = substr_replace($font, pack('N', $adjustment), $headOffset + 8, 4);
        }

        return $font;
    }

    private function parse(): void
    {
        if (strlen($this->data) < 12) {
            throw new RuntimeException('Ungültige Schriftdatei.');
        }
        $version = substr($this->data, 0, 4);
        if ($version === 'OTTO') {
            throw new RuntimeException('OpenType-Schriften mit CFF-Konturen werden nicht unterstützt, bitte eine TrueType-Datei (.ttf) verwenden.');
        }
        if ($version !== "\x00\x01\x00\x00" && $version !== 'true') {
            throw new RuntimeException('Die Datei ist keine TrueType-Schrift (.ttf).');
        }

        $numTables = $this->u16(4);
        for ($i = 0; $i < $numTables; $i++) {
            $record = 12 + 16 * $i;
            $tag = substr($this->data, $record, 4);
            $this->tables[$tag] = ['offset' => $this->u32($record + 8), 'length' => $this->u32($record + 12)];
        }
        foreach (['head', 'hhea', 'hmtx', 'maxp', 'cmap', 'loca', 'glyf'] as $required) {
            if (!isset($this->tables[$required])) {
                throw new RuntimeException("Die Schriftdatei enthält keine \"{$required}\"-Tabelle und kann nicht eingebettet werden.");
            }
        }

        $head = $this->tables['head']['offset'];
        $this->unitsPerEm = max(1, $this->u16($head + 18));
        $this->bbox = [$this->i16($head + 36), $this->i16($head + 38), $this->i16($head + 40), $this->i16($head + 42)];
        $this->indexToLocFormat = $this->i16($head + 50);

        $this->numGlyphs = $this->u16($this->tables['maxp']['offset'] + 4);

        $hhea = $this->tables['hhea']['offset'];
        $this->ascent = $this->i16($hhea + 4);
        $this->descent = $this->i16($hhea + 6);
        $numberOfHMetrics = max(1, $this->u16($hhea + 34));

        $hmtx = $this->tables['hmtx']['offset'];
        $last = 0;
        for ($glyph = 0; $glyph < $this->numGlyphs; $glyph++) {
            if ($glyph < $numberOfHMetrics) {
                $last = $this->u16($hmtx + 4 * $glyph);
            }
            $this->advances[$glyph] = $last;
        }

        $this->capHeight = (int) round($this->ascent * 0.7);
        if (isset($this->tables['OS/2'])) {
            $os2 = $this->tables['OS/2']['offset'];
            $os2Version = $this->u16($os2);
            if ($os2Version >= 2 && $this->tables['OS/2']['length'] >= 90) {
                $this->capHeight = $this->i16($os2 + 88);
            }
        }

        $this->italicAngle = 0.0;
        $this->fixedPitch = false;
        if (isset($this->tables['post'])) {
            $post = $this->tables['post']['offset'];
            $this->italicAngle = $this->i16($post + 4) + $this->u16($post + 6) / 65536;
            $this->fixedPitch = $this->u32($post + 12) !== 0;
        }

        $this->parseCmap();
        $this->postScriptName = $this->parseName() ?? 'EmbeddedFont';
    }

    private function parseCmap(): void
    {
        $cmap = $this->tables['cmap']['offset'];
        $count = $this->u16($cmap + 2);
        $best = null;
        $bestScore = -1;
        for ($i = 0; $i < $count; $i++) {
            $record = $cmap + 4 + 8 * $i;
            $platform = $this->u16($record);
            $encoding = $this->u16($record + 2);
            $offset = $cmap + $this->u32($record + 4);
            $format = $this->u16($offset);
            $score = match (true) {
                $platform === 3 && $encoding === 10 && $format === 12 => 4,
                $platform === 0 && $format === 12 => 3,
                $platform === 3 && $encoding === 1 && $format === 4 => 2,
                $platform === 0 && $format === 4 => 1,
                default => -1,
            };
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [$offset, $format];
            }
        }
        if ($best === null) {
            throw new RuntimeException('Die Schrift enthält keine Unicode-Zeichenzuordnung (cmap).');
        }

        [$offset, $format] = $best;
        if ($format === 12) {
            $groups = $this->u32($offset + 12);
            for ($g = 0; $g < $groups; $g++) {
                $base = $offset + 16 + 12 * $g;
                $start = $this->u32($base);
                // Nur der Bereich, den WinAnsi überhaupt erreichen kann (bis ™ U+2122), wird gebraucht.
                $end = min($this->u32($base + 4), 0x2122);
                $glyph = $this->u32($base + 8);
                for ($c = $start; $c <= $end; $c++) {
                    $this->cmap[$c] = $glyph + ($c - $start);
                }
            }

            return;
        }

        $segCount = intdiv($this->u16($offset + 6), 2);
        $endCodes = $offset + 14;
        $startCodes = $endCodes + 2 * $segCount + 2;
        $idDeltas = $startCodes + 2 * $segCount;
        $idRangeOffsets = $idDeltas + 2 * $segCount;
        for ($s = 0; $s < $segCount; $s++) {
            $end = $this->u16($endCodes + 2 * $s);
            $start = $this->u16($startCodes + 2 * $s);
            $delta = $this->i16($idDeltas + 2 * $s);
            $rangeOffsetPos = $idRangeOffsets + 2 * $s;
            $rangeOffset = $this->u16($rangeOffsetPos);
            for ($c = $start; $c <= $end && $c !== 0xFFFF; $c++) {
                if ($c > 0x2122) {
                    break;
                }
                if ($rangeOffset === 0) {
                    $glyph = ($c + $delta) & 0xFFFF;
                } else {
                    $glyphPos = $rangeOffsetPos + $rangeOffset + 2 * ($c - $start);
                    $glyph = $this->u16($glyphPos);
                    if ($glyph !== 0) {
                        $glyph = ($glyph + $delta) & 0xFFFF;
                    }
                }
                if ($glyph !== 0) {
                    $this->cmap[$c] = $glyph;
                }
            }
        }
    }

    private function parseName(): ?string
    {
        if (!isset($this->tables['name'])) {
            return null;
        }
        $name = $this->tables['name']['offset'];
        $count = $this->u16($name + 2);
        $storage = $name + $this->u16($name + 4);
        foreach ([3, 1] as $wantedPlatform) {
            for ($i = 0; $i < $count; $i++) {
                $record = $name + 6 + 12 * $i;
                if ($this->u16($record) !== $wantedPlatform || $this->u16($record + 6) !== 6) {
                    continue;
                }
                $raw = substr($this->data, $storage + $this->u16($record + 10), $this->u16($record + 8));
                $value = $wantedPlatform === 3 ? (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') : $raw;
                $value = preg_replace('/[^A-Za-z0-9\-]/', '', $value) ?? '';
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /** @return int[] Glyph-Offsets relativ zur glyf-Tabelle (numGlyphs + 1 Einträge) */
    private function locations(): array
    {
        $loca = $this->tables['loca']['offset'];
        $locations = [];
        for ($glyph = 0; $glyph <= $this->numGlyphs; $glyph++) {
            $locations[] = $this->indexToLocFormat === 0 ? 2 * $this->u16($loca + 2 * $glyph) : $this->u32($loca + 4 * $glyph);
        }

        return $locations;
    }

    /** @return int[] Glyph-IDs der Bestandteile einer zusammengesetzten Glyphe */
    private function componentGlyphs(int $offset, int $length): array
    {
        if ($length < 10 || $this->i16($offset) >= 0) {
            return [];
        }
        $components = [];
        $pos = $offset + 10;
        $end = $offset + $length;
        do {
            if ($pos + 4 > $end) {
                break;
            }
            $flags = $this->u16($pos);
            $components[] = $this->u16($pos + 2);
            $pos += 4 + (($flags & 0x0001) !== 0 ? 4 : 2);
            if (($flags & 0x0008) !== 0) {
                $pos += 2;
            } elseif (($flags & 0x0040) !== 0) {
                $pos += 4;
            } elseif (($flags & 0x0080) !== 0) {
                $pos += 8;
            }
        } while (($flags & 0x0020) !== 0);

        return $components;
    }

    private function advanceOf(int $glyph): int
    {
        return (int) round(($this->advances[$glyph] ?? $this->advances[0] ?? 0) * 1000 / $this->unitsPerEm);
    }

    private function table(string $tag): string
    {
        return substr($this->data, $this->tables[$tag]['offset'], $this->tables[$tag]['length']);
    }

    private static function checksum(string $data): int
    {
        $data .= str_repeat("\0", (4 - strlen($data) % 4) % 4);
        $sum = 0;
        foreach (unpack('N*', $data) ?: [] as $value) {
            $sum = ($sum + $value) & 0xFFFFFFFF;
        }

        return $sum;
    }

    /** Unicode-Codepoint eines cp1252-Codes (WinAnsiEncoding). */
    public static function cp1252ToUnicode(int $code): int
    {
        static $map = [
            0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020, 0x87 => 0x2021,
            0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152, 0x8E => 0x017D,
            0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
            0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A, 0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
        ];

        return $map[$code] ?? $code;
    }

    private function u16(int $offset): int
    {
        return (ord($this->data[$offset]) << 8) | ord($this->data[$offset + 1]);
    }

    private function i16(int $offset): int
    {
        $value = $this->u16($offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function u32(int $offset): int
    {
        return (int) unpack('N', substr($this->data, $offset, 4))[1];
    }
}
