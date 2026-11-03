<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Bereiche in PDF-Dokumenten und -Listen mit eigener Schriftart (Migration
 * 116). Die Werte entsprechen SimplePdfWriter::ROLE_*. Ohne Zuordnung gilt
 * für alle Bereiche die Schrift von "Text", ohne diese Helvetica.
 */
enum PdfFontRole: string
{
    case Body = 'body';
    case Heading = 'heading';
    case Table = 'table';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Body => 'Text (Standard)',
            self::Heading => 'Überschriften',
            self::Table => 'Tabellen und Summen',
            self::Footer => 'Fußzeile',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Body => 'Adressen, Einleitungen, Hinweise, Rich-Text und alles ohne eigene Zuordnung.',
            self::Heading => 'Dokumenttitel (Angebot, Rechnung, Preisliste …) und Abschnittsüberschriften.',
            self::Table => 'Positionstabellen mit Kopfzeile sowie Zwischen- und Gesamtsummen.',
            self::Footer => 'Fußzeile mit Firmen- und Bankdaten.',
        };
    }
}
