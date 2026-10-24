<?php

declare(strict_types=1);

namespace App\Infrastructure\RichText;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Bereinigt ein vom Rich-Text-Editor (contenteditable + execCommand, siehe
 * resources/views/settings/offer-pdf-template.php und
 * invoice-pdf-template.php) geliefertes HTML-Fragment auf eine feste
 * Allowlist an Block-/Inline-Elementen, BEVOR es in offer_pdf_settings bzw.
 * invoice_pdf_settings gespeichert wird (siehe Update*PdfSettingsHandler).
 *
 * Zwei Verteidigungslinien in einer: erstens die übliche XSS-Absicherung
 * (kein <script>, kein onclick=, kein javascript:-Link — hier ohnehin
 * einfach, da grundsätzlich ALLE Attribute entfernt werden bis auf ein
 * geprüftes text-align im style-Attribut), zweitens Kompatibilität mit
 * HtmlToRichTextParser/RichTextPdfRenderer, die nur genau diese Elemente
 * verstehen — alles andere würde beim PDF-Export ohnehin ignoriert, wird
 * hier aber schon beim Speichern entfernt, damit der Admin sieht, was
 * tatsächlich ankommt, falls er das HTML z. B. ohne JavaScript direkt in
 * der Textarea von Hand bearbeitet.
 *
 * Unbekannte Elemente werden nicht samt Inhalt gelöscht, sondern "unwrapped"
 * (das Element selbst entfernt, sein Inhalt bleibt an derselben Stelle
 * erhalten) — nur <script>/<style> werden komplett samt Inhalt entfernt.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'ul', 'ol', 'li'];

    /** Tags, bei denen ein style-Attribut mit ausschließlich text-align erhalten bleibt. */
    private const ALLOW_ALIGN_STYLE_ON = ['p', 'div', 'h1', 'h2', 'h3', 'li'];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        // In ein <div> gewickelt, damit loadHTML() ein einzelnes Wurzelelement
        // hat, an dem wir uns nach dem Parsen wieder orientieren können.
        $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $doc->getElementsByTagName('div')->item(0);
        if ($root === null) {
            return null;
        }

        $this->cleanChildren($root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $serialized = $doc->saveHTML($child);
            if ($serialized !== false) {
                $result .= $serialized;
            }
        }

        $result = trim($result);

        if ($result === '' || trim(strip_tags($result)) === '') {
            // Kein tatsächlicher Textinhalt (z. B. nur ein leeres <p></p> oder
            // <p><br></p>, wie es ein geleertes contenteditable-Feld liefert)
            // -> wie ein leeres Feld behandeln, damit z. B. "Leer lassen,
            // damit das PDF wie bisher aussieht" (siehe settings.offer_pdf_
            // template.intro.hint) auch wirklich zu NULL in der Datenbank
            // führt statt zu einem leeren, aber nicht-NULL HTML-Fragment.
            return null;
        }

        return $result;
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if ($child instanceof DOMComment) {
                $node->removeChild($child);

                continue;
            }

            if (!$child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script', 'style'], true)) {
                    $node->removeChild($child);

                    continue;
                }

                // Unwrap: erst die Kinder bereinigen, dann eine Ebene nach oben
                // ziehen und das nicht erlaubte Element selbst verwerfen.
                $this->cleanChildren($child);
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $this->stripAttributesExceptAlignment($child, $tag);
            $this->cleanChildren($child);
        }
    }

    private function stripAttributesExceptAlignment(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if ($attribute->name === 'style' && in_array($tag, self::ALLOW_ALIGN_STYLE_ON, true)) {
                $align = $this->extractTextAlign($attribute->value);
                if ($align !== null) {
                    $element->setAttribute('style', 'text-align: ' . $align . ';');

                    continue;
                }
            }

            $element->removeAttribute($attribute->name);
        }
    }

    private function extractTextAlign(string $style): ?string
    {
        if (preg_match('/text-align\s*:\s*(left|center|right)/i', $style, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
