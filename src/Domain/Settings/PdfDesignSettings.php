<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Gemeinsames Design-Grundgerüst für Angebots- und Rechnungs-PDF (siehe
 * OfferPdfGenerator/InvoicePdfGenerator), Singleton-Zeile analog zu
 * InvoiceSettings/CompanySettings. Bewusst auf zwei risikofreie, additive
 * Eigenschaften beschränkt (siehe Projektentscheidung: eine komplette
 * Neuentwicklung der PDF-Generatoren für frei positionierbare Blöcke ist ein
 * deutlich größeres, separates Vorhaben) — Farbe und Logo-Seite betreffen
 * nur EINZELNE, klar abgegrenzte Zeichenaufrufe und können daher gefahrlos
 * in die bestehende, sequenzielle Zeichenlogik eingesetzt werden, ohne deren
 * Seitenumbruch-/Reihenfolge-Annahmen zu verändern.
 */
final class PdfDesignSettings
{
    public function __construct(
        public int $accentColorR, // 0-255
        public int $accentColorG,
        public int $accentColorB,
        public string $logoPosition, // 'left' | 'right', siehe LogoPosition-Konstanten
        // Migration 110: Beträge auf Dokumenten mit "EUR" (code) oder "€" (symbol).
        public string $currencyDisplay = self::CURRENCY_DISPLAY_CODE,
    ) {
    }

    public const CURRENCY_DISPLAY_CODE = 'code';
    public const CURRENCY_DISPLAY_SYMBOL = 'symbol';

    /** @return string[] */
    public static function validCurrencyDisplays(): array
    {
        return [self::CURRENCY_DISPLAY_CODE, self::CURRENCY_DISPLAY_SYMBOL];
    }

    /** Anhängsel hinter Beträgen, inkl. führendem Leerzeichen: " EUR" bzw. " €". */
    public function currencySuffix(): string
    {
        return $this->currencyDisplay === self::CURRENCY_DISPLAY_SYMBOL ? ' €' : ' EUR';
    }

    public const LOGO_POSITION_LEFT = 'left';
    public const LOGO_POSITION_RIGHT = 'right';

    /** @return string[] Gültige Werte für logoPosition, für Validierung in UpdatePdfDesignSettingsHandler. */
    public static function validLogoPositions(): array
    {
        return [self::LOGO_POSITION_LEFT, self::LOGO_POSITION_RIGHT];
    }

    /** RGB-Farbstring im von SimplePdfWriter erwarteten Format ("r g b", je 0.0-1.0), siehe SimplePdfWriter::text()/line()/filledRect(). */
    public function accentColorPdfString(): string
    {
        return sprintf(
            '%s %s %s',
            round($this->accentColorR / 255, 3),
            round($this->accentColorG / 255, 3),
            round($this->accentColorB / 255, 3)
        );
    }

    /**
     * Hell abgetönte Variante der Akzentfarbe (für die Tabellenkopf-Füllung)
     * — mischt die Akzentfarbe zu 8% mit Weiß. Der Mischanteil ist bewusst
     * so gewählt, dass die DEFAULT-Akzentfarbe Schwarz (0,0,0) exakt den
     * bisherigen, fest hartkodierten Grauton 0.92/0.92/0.92 ergibt (siehe
     * Klassen-Doc: "entspricht optisch exakt dem bisherigen, hartkodierten
     * Verhalten") — bei 0,0,0 bleibt also am Erscheinungsbild ohne bewusste
     * Änderung durch den Nutzer nichts anders.
     */
    public function accentColorTintPdfString(): string
    {
        $mix = static fn (int $channel): float => round((($channel / 255) * 0.08) + (1 - 0.08), 3);

        return sprintf('%s %s %s', $mix($this->accentColorR), $mix($this->accentColorG), $mix($this->accentColorB));
    }

    /**
     * Abgetönte Variante der Akzentfarbe für Trennlinien — mischt die
     * Akzentfarbe zu 60% mit Weiß. Der Mischanteil ist bewusst so gewählt,
     * dass die DEFAULT-Akzentfarbe Schwarz (0,0,0) exakt den bisherigen,
     * fest hartkodierten Grauton 0.4/0.4/0.4 der Trennlinien ergibt (siehe
     * accentColorTintPdfString() für die identische Begründung beim
     * Tabellenkopf — Trennlinien und Tabellenkopf hatten VOR diesem Feature
     * unterschiedliche Grautöne, daher zwei getrennte Methoden mit
     * unterschiedlichem Mischanteil statt einer gemeinsamen).
     */
    public function accentColorLinePdfString(): string
    {
        $mix = static fn (int $channel): float => round((($channel / 255) * 0.6) + (1 - 0.6), 3);

        return sprintf('%s %s %s', $mix($this->accentColorR), $mix($this->accentColorG), $mix($this->accentColorB));
    }

    /** Hex-Darstellung ("#rrggbb") für das Farbfeld im Einstellungsformular. */
    public function accentColorHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->accentColorR, $this->accentColorG, $this->accentColorB);
    }
}
