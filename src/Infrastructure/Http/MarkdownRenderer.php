<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Kleiner, selbst geschriebener Markdown-zu-HTML-Konverter (keine externe
 * Bibliothek verfügbar/erlaubt). Deckt bewusst nur die Teilmenge ab, die der
 * WYSIWYG-Editor der Rechtstexte (Vermietbedingungen/Impressum/
 * Datenschutzerklärung, siehe resources/views/settings/legal.php) erzeugen
 * kann: Absätze, Überschriften (#/##/###), **fett**, *kursiv*, Listen
 * (-/*  bzw. 1.) und [Text](URL)-Links. Reiner Text wird konsequent per
 * htmlspecialchars() escaped, damit gespeicherte Rechtstexte niemals
 * unescapetes HTML/JS in die Seite einschleusen können (XSS).
 */
final class MarkdownRenderer
{
    public static function toHtml(?string $markdown): string
    {
        $markdown = str_replace("\r\n", "\n", $markdown ?? '');
        $lines = explode("\n", $markdown);

        $html = '';
        $paragraphBuffer = [];
        $listType = null;
        $listItems = [];

        $flushParagraph = static function () use (&$paragraphBuffer, &$html): void {
            if ($paragraphBuffer !== []) {
                $html .= '<p>' . implode(' ', $paragraphBuffer) . "</p>\n";
                $paragraphBuffer = [];
            }
        };

        $flushList = static function () use (&$listType, &$listItems, &$html): void {
            if ($listType !== null) {
                $html .= "<{$listType}>\n";
                foreach ($listItems as $item) {
                    $html .= "<li>{$item}</li>\n";
                }
                $html .= "</{$listType}>\n";
                $listType = null;
                $listItems = [];
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushParagraph();
                $flushList();

                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.*)$/', $trimmed, $m) === 1) {
                $flushParagraph();
                $flushList();
                $level = strlen($m[1]);
                $html .= "<h{$level}>" . self::inline($m[2]) . "</h{$level}>\n";

                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/', $trimmed, $m) === 1) {
                $flushParagraph();
                if ($listType !== null && $listType !== 'ul') {
                    $flushList();
                }
                $listType = 'ul';
                $listItems[] = self::inline($m[1]);

                continue;
            }

            if (preg_match('/^\d+\.\s+(.*)$/', $trimmed, $m) === 1) {
                $flushParagraph();
                if ($listType !== null && $listType !== 'ol') {
                    $flushList();
                }
                $listType = 'ol';
                $listItems[] = self::inline($m[1]);

                continue;
            }

            $flushList();
            $paragraphBuffer[] = self::inline($trimmed);
        }

        $flushParagraph();
        $flushList();

        return $html;
    }

    /**
     * Inline-Formatierung EINER Textzeile. Links werden zuerst (auf dem noch
     * unescapten Text) erkannt und durch Platzhalter ersetzt, damit weder das
     * anschließende htmlspecialchars() noch die Fett-/Kursiv-Ersetzung das
     * bereits fertige <a>-Tag anfasst — Label und URL werden dabei separat
     * und korrekt escaped.
     */
    private static function inline(string $text): string
    {
        $links = [];
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $m) use (&$links): string {
            $label = $m[1];
            $url = $m[2];
            $index = count($links);

            if (preg_match('#^(https?://|mailto:)#i', $url) === 1) {
                $links[$index] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
                    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            } else {
                // Unbekanntes/unsicheres Schema (z. B. "javascript:") wird
                // nur als reiner Text übernommen, nie als Link gerendert.
                $links[$index] = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            }

            return "\0LINK{$index}\0";
        }, $text) ?? $text;

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;

        return preg_replace_callback('/\x00LINK(\d+)\x00/', static function (array $m) use ($links): string {
            return $links[(int) $m[1]] ?? '';
        }, $escaped) ?? $escaped;
    }
}
