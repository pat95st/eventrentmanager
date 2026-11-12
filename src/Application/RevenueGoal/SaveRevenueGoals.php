<?php

declare(strict_types=1);

namespace App\Application\RevenueGoal;

use App\Application\Command\CommandInterface;

/**
 * Speichert die Umsatzziele-Tabelle der Einstellungsseite in einem Rutsch:
 * bestehende Ziele (Name, Art, Standardbetrag, Betrag für $year) und
 * optional ein neues Ziel. Alle Werte sind rohe Formulareingaben.
 */
final class SaveRevenueGoals implements CommandInterface
{
    /**
     * @param array<string, array{name: string, kind: string, amount: string, year_amount: string}> $goals Ziel-ID => Eingaben
     * @param array{name: string, kind: string, amount: string, year_amount: string} $new neues Ziel; wird ignoriert, wenn Name und Betrag leer sind
     */
    public function __construct(
        public readonly int $year,
        public readonly array $goals,
        public readonly array $new,
    ) {
    }
}
