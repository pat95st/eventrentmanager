<?php

declare(strict_types=1);

namespace App\Domain\Settings\PdfLayout;

/**
 * Beschreibt EINEN im visuellen Editor verschiebbaren Block eines
 * Dokumenttyps: Anzeigename, Default-Box (nur Orientierungshilfe zum
 * Ziehen, siehe PdfLayoutElementPosition-Klassendoc) und ob eine
 * Schriftgröße eingestellt werden kann (bei reinen Bild-/Block-Elementen
 * wie "logo" nicht sinnvoll).
 */
final class PdfLayoutElementDefinition
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly float $defaultXMm,
        public readonly float $defaultYMm,
        public readonly ?float $defaultWidthMm,
        public readonly bool $supportsFontSize,
        public readonly ?float $defaultFontSize = null,
    ) {
    }
}
