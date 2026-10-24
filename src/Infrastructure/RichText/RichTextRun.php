<?php

declare(strict_types=1);

namespace App\Infrastructure\RichText;

/**
 * Ein zusammenhängendes Textstück mit einheitlicher Inline-Formatierung
 * innerhalb eines RichTextBlock — z. B. "wichtig" in "Bitte **wichtig**
 * beachten" wäre ein eigener Run mit bold=true, umgeben von zwei
 * unformatierten Runs. Entsteht beim Parsen des vom Editor gelieferten
 * HTML-Fragments (siehe HtmlToRichTextParser) und wird beim PDF-Export
 * wieder in einzelne text()-Aufrufe an App\Infrastructure\Pdf\
 * SimplePdfWriter übersetzt (siehe App\Infrastructure\Pdf\
 * RichTextPdfRenderer).
 */
final class RichTextRun
{
    public function __construct(
        public readonly string $text,
        public readonly bool $bold = false,
        public readonly bool $italic = false,
        public readonly bool $underline = false,
    ) {
    }
}
