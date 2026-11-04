<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Bereiche der Oberfläche (Frontend) mit eigener Schriftart. Die Schriften
 * kommen aus derselben Bibliothek wie die PDF-Schriften (Einstellungen ›
 * Schriftarten, Migration 116); die Zuordnung liegt ebenfalls in
 * pdf_font_assignments, mit dem Präfix "ui_". Ohne Zuordnung gilt die Schrift
 * von "Text", ohne diese die Systemschrift.
 */
enum UiFontRole: string
{
    case Body = 'ui_body';
    case Heading = 'ui_heading';
    case Navigation = 'ui_nav';
    case Table = 'ui_table';
    case Numbers = 'ui_numbers';

    public function label(): string
    {
        return match ($this) {
            self::Body => 'Text (Standard)',
            self::Heading => 'Seitentitel und Überschriften',
            self::Navigation => 'Navigation',
            self::Table => 'Tabellen und Listen',
            self::Numbers => 'Nummern und Codes',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Body => 'Die gesamte Oberfläche inklusive Formulare, Buttons und Anmeldeseite – alles ohne eigene Zuordnung.',
            self::Heading => 'Seitentitel und Abschnittsüberschriften.',
            self::Navigation => 'Kopfleiste mit Hauptmenü, Handy-Menü und Fußzeile.',
            self::Table => 'Tabellen wie Artikel-, Kunden- und Rechnungslisten.',
            self::Numbers => 'Inventur-, Rechnungs- und Kundennummern und andere Codes (bisher Festbreitenschrift).',
        };
    }

    /** CSS-Selektoren, für die die Schrift gilt. */
    public function selector(): string
    {
        return match ($this) {
            self::Body => 'body',
            self::Heading => 'h1, h2, h3, h4',
            self::Navigation => 'body > header, body > footer',
            self::Table => 'table',
            self::Numbers => '.font-mono, code, kbd, samp',
        };
    }

    /** Systemschrift, wenn nichts zugeordnet ist (Tailwind-Standard). */
    public function fallbackStack(): string
    {
        return $this === self::Numbers
            ? 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace'
            : 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';
    }
}
