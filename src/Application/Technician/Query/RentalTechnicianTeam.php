<?php

declare(strict_types=1);

namespace App\Application\Technician\Query;

use App\Domain\Technician\Trade;
use App\Domain\User\User;

/** Techniker und Gewerke einer Veranstaltung (Migration 134). */
final class RentalTechnicianTeam
{
    /**
     * @param list<array{assignment: \App\Domain\Technician\RentalTechnician, user: User|null, tradeNames: string[], requiresConfirmation: bool}> $rows
     * @param Trade[] $allTrades
     * @param string[] $rentalTradeIds
     * @param User[] $availableTechnicians aktive Techniker, die noch nicht zugeordnet sind
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $allTrades,
        public readonly array $rentalTradeIds,
        public readonly array $availableTechnicians,
    ) {
    }

    /** @return string[] Namen der benötigten Gewerke */
    public function rentalTradeNames(): array
    {
        return array_values(array_map(static fn (Trade $t) => $t->name, array_filter($this->allTrades, fn (Trade $t) => in_array($t->id, $this->rentalTradeIds, true))));
    }

    /** @return string[] benötigte Gewerke, denen noch kein (nicht abgesagter) Techniker zugeordnet ist */
    public function uncoveredTradeNames(): array
    {
        $covered = [];
        foreach ($this->rows as $row) {
            if ($row['assignment']->status->grantsAccess()) {
                $covered = array_merge($covered, $row['assignment']->tradeIds);
            }
        }

        return array_values(array_map(static fn (Trade $t) => $t->name, array_filter($this->allTrades, fn (Trade $t) => in_array($t->id, $this->rentalTradeIds, true) && !in_array($t->id, $covered, true))));
    }
}
