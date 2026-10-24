<?php

declare(strict_types=1);

namespace App\Infrastructure\RichText;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Übersetzt ein bereits bereinigtes HTML-Fragment (siehe HtmlSanitizer, das
 * VOR dem Speichern läuft — dieser Parser vertraut also bewusst darauf, dass
 * nur die dort erlaubten Elemente vorkommen, prüft aber trotzdem defensiv,
 * da direkt bearbeitete Altbestände denkbar sind) in eine Liste von
 * RichTextBlock-Objekten, wie sie App\Infrastructure\Pdf\
 * RichTextPdfRenderer zum Zeichnen im PDF braucht.
 *
 * <p>/<div> -> Absatz, <h1-3> -> Überschrift, <ul>/<ol> -> je ein Block pro
 * <li> (Bullet- bzw. Nummerierungspunkt). Verschachtelte <strong>/<b>,
 * <em>/<i>, <u> ergeben die Inline-Formatierung der RichTextRuns, <br>
 * erzwingt einen Zeilenumbruch INNERHALB eines Blocks (siehe
 * RichTextPdfRenderer::wrapRuns()).
 */
final class HtmlToRichTextParser
{
    /** @return list<RichTextBlock> */
    public function parse(?string $html): array
    {
        if ($html === null || trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return [];
        }

        $blocks = [];
        foreach (iterator_to_array($root->childNodes) as $node) {
            $this->consumeNode($node, $blocks);
        }

        return $blocks;
    }

    /** @param list<RichTextBlock> $blocks */
    private function consumeNode(DOMNode $node, array &$blocks): void
    {
        if ($node instanceof DOMText) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? $node->textContent);
            if ($text !== '') {
                $blocks[] = new RichTextBlock(RichTextBlock::TYPE_PARAGRAPH, RichTextBlock::ALIGN_LEFT, [new RichTextRun($text)]);
            }

            return;
        }

        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        switch ($tag) {
            case 'p':
            case 'div':
                $this->appendBlockIfNotEmpty($blocks, RichTextBlock::TYPE_PARAGRAPH, $node);

                return;
            case 'h1':
                $this->appendBlockIfNotEmpty($blocks, RichTextBlock::TYPE_HEADING_1, $node);

                return;
            case 'h2':
                $this->appendBlockIfNotEmpty($blocks, RichTextBlock::TYPE_HEADING_2, $node);

                return;
            case 'h3':
                $this->appendBlockIfNotEmpty($blocks, RichTextBlock::TYPE_HEADING_3, $node);

                return;
            case 'ul':
            case 'ol':
                $this->consumeList($node, $tag === 'ol', $blocks);

                return;
            case 'br':
                return;
            default:
                // Unbekanntes Element (sollte dank HtmlSanitizer nicht vorkommen):
                // Inhalt als normalen Absatz behandeln statt ihn zu verlieren.
                $this->appendBlockIfNotEmpty($blocks, RichTextBlock::TYPE_PARAGRAPH, $node);
        }
    }

    /** @param list<RichTextBlock> $blocks */
    private function consumeList(DOMElement $listNode, bool $ordered, array &$blocks): void
    {
        $index = 1;
        foreach (iterator_to_array($listNode->childNodes) as $child) {
            if (!$child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $runs = $this->collectRuns($child);
            if ($runs === []) {
                continue;
            }

            $blocks[] = new RichTextBlock(
                $ordered ? RichTextBlock::TYPE_NUMBERED : RichTextBlock::TYPE_BULLET,
                $this->alignOf($child),
                $runs,
                $ordered ? $index : null,
            );
            $index++;
        }
    }

    /** @param list<RichTextBlock> $blocks */
    private function appendBlockIfNotEmpty(array &$blocks, string $type, DOMElement $node): void
    {
        $runs = $this->collectRuns($node);
        if ($runs !== []) {
            $blocks[] = new RichTextBlock($type, $this->alignOf($node), $runs);
        }
    }

    private function alignOf(DOMElement $element): string
    {
        $style = $element->getAttribute('style');
        if (preg_match('/text-align\s*:\s*(left|center|right)/i', $style, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return RichTextBlock::ALIGN_LEFT;
    }

    /** @return list<RichTextRun> */
    private function collectRuns(DOMNode $node): array
    {
        $runs = [];
        $this->walkInline($node, false, false, false, $runs);

        // Führende/abschließende reine Leerzeichen-Runs abschneiden, die durch
        // Zeilenumbrüche im Editor-Quelltext entstehen können.
        while ($runs !== [] && trim($runs[0]->text) === '') {
            array_shift($runs);
        }
        while ($runs !== [] && trim($runs[count($runs) - 1]->text) === '') {
            array_pop($runs);
        }

        return $runs;
    }

    /** @param list<RichTextRun> $runs */
    private function walkInline(DOMNode $node, bool $bold, bool $italic, bool $underline, array &$runs): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = preg_replace('/\s+/', ' ', $child->textContent) ?? $child->textContent;
                if ($text !== '') {
                    $runs[] = new RichTextRun($text, $bold, $italic, $underline);
                }

                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'br') {
                $runs[] = new RichTextRun("\n", $bold, $italic, $underline);

                continue;
            }

            $childBold = $bold || in_array($tag, ['strong', 'b'], true);
            $childItalic = $italic || in_array($tag, ['em', 'i'], true);
            $childUnderline = $underline || $tag === 'u';

            $this->walkInline($child, $childBold, $childItalic, $childUnderline, $runs);
        }
    }
}
