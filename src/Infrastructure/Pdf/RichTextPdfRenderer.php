<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Infrastructure\RichText\HtmlToRichTextParser;
use App\Infrastructure\RichText\RichTextBlock;
use App\Infrastructure\RichText\RichTextRun;

/**
 * Zeichnet eine Liste von RichTextBlock-Objekten (siehe
 * App\Infrastructure\RichText\HtmlToRichTextParser) auf einen
 * SimplePdfWriter — die eigentliche "Word/LibreOffice-artige" Formatierung
 * (fett, kursiv, unterstrichen, Überschriften, Aufzählungen, Ausrichtung)
 * der Einleitungs-/Schlusstexte in Angebots- und Rechnungs-PDF (siehe
 * OfferPdfGenerator/InvoicePdfGenerator).
 *
 * Wie schon der bestehende SimplePdfWriter arbeitet auch dies ohne echte
 * Font-Metriken (Best-effort-Zeilenumbruch über eine grobe durchschnittliche
 * Zeichenbreite je Schriftgröße/-schnitt) — für die kurzen, meist wenige
 * Absätze langen Einleitungs-/Schlusstexte in der Praxis ausreichend genau,
 * siehe bestehende wrapText()-Methoden in den beiden Generatoren, die
 * dasselbe Prinzip verwenden.
 */
final class RichTextPdfRenderer
{
    /** @var array<string, float> Schriftgröße je Blocktyp. */
    private const FONT_SIZES = [
        RichTextBlock::TYPE_HEADING_1 => 14.0,
        RichTextBlock::TYPE_HEADING_2 => 12.0,
        RichTextBlock::TYPE_HEADING_3 => 10.5,
        RichTextBlock::TYPE_PARAGRAPH => 9.0,
        RichTextBlock::TYPE_BULLET => 9.0,
        RichTextBlock::TYPE_NUMBERED => 9.0,
    ];

    private const LIST_INDENT = 14.0;
    private const PARAGRAPH_SPACING_FACTOR = 0.45;

    public function __construct(private readonly HtmlToRichTextParser $parser)
    {
    }

    /** @return list<RichTextBlock> */
    public function parse(?string $html): array
    {
        return $this->parser->parse($html);
    }

    /**
     * Zeichnet $blocks ab Position (x, y) mit Zeilenbreite $maxWidth.
     * Bricht eine Zeile, die $bottomLimit unterschreiten würde, nur dann auf
     * eine neue Seite um, wenn $newPage übergeben wurde — dieses Callable
     * erledigt Seitenzahl-Erhöhung + Kopfbereich-Neuzeichnung (siehe Aufrufer
     * in OfferPdfGenerator/InvoicePdfGenerator) und liefert die neue
     * Start-y-Position zurück. Ohne $newPage (z. B. für den Einleitungstext,
     * der wie schon bisher nicht separat paginiert wird, siehe dortiger
     * Aufrufer) wird einfach weitergeschrieben, auch über $bottomLimit
     * hinaus — bestehende, bewusst unveränderte Grenze dieses Features.
     *
     * @param list<RichTextBlock> $blocks
     */
    public function render(
        SimplePdfWriter $writer,
        array $blocks,
        float $x,
        float $y,
        float $maxWidth,
        float $bottomLimit,
        ?callable $newPage = null
    ): float {
        foreach ($blocks as $block) {
            $fontSize = self::FONT_SIZES[$block->type] ?? 9.0;
            $lineHeight = $fontSize * 1.4;
            $forceBold = str_starts_with($block->type, 'heading');
            $isListItem = $block->type === RichTextBlock::TYPE_BULLET || $block->type === RichTextBlock::TYPE_NUMBERED;
            $indent = $isListItem ? self::LIST_INDENT : 0.0;

            $lines = $this->wrapRuns($block->runs, $maxWidth - $indent, $fontSize, $forceBold);

            foreach ($lines as $lineIndex => $line) {
                if ($y - $lineHeight < $bottomLimit && $newPage !== null) {
                    $y = $newPage();
                }

                $lineWidth = $this->lineWidth($line, $fontSize, $forceBold);
                $contentWidth = $maxWidth - $indent;
                $startX = $x + $indent;

                if ($block->align === RichTextBlock::ALIGN_CENTER) {
                    $startX += max(0.0, ($contentWidth - $lineWidth) / 2);
                } elseif ($block->align === RichTextBlock::ALIGN_RIGHT) {
                    $startX += max(0.0, $contentWidth - $lineWidth);
                }

                if ($lineIndex === 0 && $isListItem) {
                    $marker = $block->type === RichTextBlock::TYPE_NUMBERED
                        ? ($block->listIndex ?? 1) . '.'
                        : "\u{2022}";
                    $writer->text($x, $y, $marker, $fontSize, $forceBold);
                }

                $runX = $startX;
                foreach ($line as $run) {
                    $isBold = $forceBold || $run->bold;
                    $writer->text($runX, $y, $run->text, $fontSize, $isBold, $run->italic);

                    $width = $this->textWidth($run->text, $fontSize, $isBold);
                    if ($run->underline && trim($run->text) !== '') {
                        $writer->line($runX, $y - 1.5, $runX + $width, $y - 1.5, 0.6);
                    }

                    $runX += $width;
                }

                $y -= $lineHeight;
            }

            $y -= $lineHeight * self::PARAGRAPH_SPACING_FACTOR;
        }

        return $y;
    }

    /**
     * @param list<RichTextRun> $runs
     * @return list<list<RichTextRun>> Fertig umgebrochene Zeilen, jede eine
     *     Liste zusammenhängender Runs gleicher Formatierung.
     */
    private function wrapRuns(array $runs, float $maxWidth, float $fontSize, bool $forceBold): array
    {
        /** @var list<array{text: string, bold: bool, italic: bool, underline: bool, break: bool}> $words */
        $words = [];
        foreach ($runs as $run) {
            $segments = preg_split('/(\n)/', $run->text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$run->text];
            foreach ($segments as $segment) {
                if ($segment === "\n") {
                    $words[] = ['text' => '', 'bold' => $run->bold, 'italic' => $run->italic, 'underline' => $run->underline, 'break' => true];

                    continue;
                }

                $tokens = preg_split('/(\s+)/', $segment, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($tokens as $token) {
                    $words[] = ['text' => $token, 'bold' => $run->bold, 'italic' => $run->italic, 'underline' => $run->underline, 'break' => false];
                }
            }
        }

        $lines = [];
        /** @var list<RichTextRun> $current */
        $current = [];
        $currentWidth = 0.0;

        foreach ($words as $word) {
            if ($word['break']) {
                $lines[] = $this->mergeAdjacent($current);
                $current = [];
                $currentWidth = 0.0;

                continue;
            }

            $isWhitespace = trim($word['text']) === '';
            $wordWidth = $this->textWidth($word['text'], $fontSize, $forceBold || $word['bold']);

            if ($isWhitespace) {
                if ($current !== []) {
                    $current[] = new RichTextRun($word['text'], $word['bold'], $word['italic'], $word['underline']);
                    $currentWidth += $wordWidth;
                }

                continue;
            }

            if ($current !== [] && $currentWidth + $wordWidth > $maxWidth) {
                $lines[] = $this->mergeAdjacent($current);
                $current = [];
                $currentWidth = 0.0;
            }

            $current[] = new RichTextRun($word['text'], $word['bold'], $word['italic'], $word['underline']);
            $currentWidth += $wordWidth;
        }

        if ($current !== [] || $lines === []) {
            $lines[] = $this->mergeAdjacent($current);
        }

        return $lines;
    }

    /**
     * @param list<RichTextRun> $runs
     * @return list<RichTextRun>
     */
    private function mergeAdjacent(array $runs): array
    {
        $merged = [];
        foreach ($runs as $run) {
            $lastIndex = count($merged) - 1;
            $last = $lastIndex >= 0 ? $merged[$lastIndex] : null;

            if ($last !== null && $last->bold === $run->bold && $last->italic === $run->italic && $last->underline === $run->underline) {
                $merged[$lastIndex] = new RichTextRun($last->text . $run->text, $last->bold, $last->italic, $last->underline);
            } else {
                $merged[] = $run;
            }
        }

        if ($merged !== []) {
            $lastIndex = count($merged) - 1;
            $trimmed = rtrim($merged[$lastIndex]->text);
            if ($trimmed === '') {
                array_pop($merged);
            } else {
                $merged[$lastIndex] = new RichTextRun($trimmed, $merged[$lastIndex]->bold, $merged[$lastIndex]->italic, $merged[$lastIndex]->underline);
            }
        }

        return $merged;
    }

    /** @param list<RichTextRun> $runs */
    private function lineWidth(array $runs, float $fontSize, bool $forceBold): float
    {
        $width = 0.0;
        foreach ($runs as $run) {
            $width += $this->textWidth($run->text, $fontSize, $forceBold || $run->bold);
        }

        return $width;
    }

    /**
     * Grobe Zeichenbreiten-Schätzung ohne echte Font-Metriken, siehe
     * Klassendoc — fette Laufweite geringfügig breiter angesetzt als
     * normale/kursive, analog zur bereits bestehenden Formel in
     * OfferPdfGenerator::wrapText()/InvoicePdfGenerator::wrapText()
     * (fontSize * 0.52).
     */
    private function textWidth(string $text, float $fontSize, bool $bold): float
    {
        $avgCharWidth = $fontSize * ($bold ? 0.58 : 0.52);

        return mb_strlen($text) * $avgCharWidth;
    }
}
