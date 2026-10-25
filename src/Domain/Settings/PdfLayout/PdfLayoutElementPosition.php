<?php

declare(strict_types=1);

namespace App\Domain\Settings\PdfLayout;

/**
 * Eine vom Administrator im visuellen Editor (siehe
 * PdfLayoutSettingsController) frei gesetzte Position für EINEN Block
 * (z. B. "logo", "intro_text") eines Dokumenttyps. Millimeter, Ursprung
 * oben links — siehe Migration 091 für die Begründung.
 */
final class PdfLayoutElementPosition
{
    public function __construct(
        public readonly float $xMm,
        public readonly float $yMm,
        public readonly ?float $widthMm = null,
        public readonly ?float $fontSize = null,
    ) {
    }

    /**
     * Rechnet in PDF-Punkte um (Ursprung unten links, wie von
     * SimplePdfWriter erwartet) — siehe MM_TO_PT-Konstante in den
     * *PdfGenerator-Klassen, hier bewusst dieselbe Zahl, damit ein im
     * Editor bei 45mm von oben platzierter Block exakt dort erscheint.
     *
     * @return array{x: float, y: float}
     */
    public function toPdfPoint(float $pageHeightPt): array
    {
        $mmToPt = 2.83465;

        return [
            'x' => $this->xMm * $mmToPt,
            'y' => $pageHeightPt - ($this->yMm * $mmToPt),
        ];
    }

    public function widthPt(): ?float
    {
        return $this->widthMm !== null ? $this->widthMm * 2.83465 : null;
    }
}
