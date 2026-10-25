<?php

declare(strict_types=1);

namespace App\Domain\Settings\PdfLayout;

/**
 * Alle vom Administrator für EINEN Dokumenttyp gespeicherten
 * Block-Positionen, keyed nach element_key (siehe
 * PdfLayoutBlueprint::ELEMENTS). Ein Block ohne Eintrag ist nicht
 * angepasst — get() liefert dann null, und der jeweilige *PdfGenerator
 * zeichnet ihn über seine bisherige, fest kodierte Logik (siehe
 * Migration 091 für die Begründung dieses Fallback-Verhaltens).
 */
final class PdfLayoutSettings
{
    /** @param array<string, PdfLayoutElementPosition> $positions */
    public function __construct(private readonly array $positions = [])
    {
    }

    public function get(string $elementKey): ?PdfLayoutElementPosition
    {
        return $this->positions[$elementKey] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->positions === [];
    }
}
