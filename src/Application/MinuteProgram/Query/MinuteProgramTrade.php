<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

/** A trade (Gewerk) as offered by a minute program, with its display color (migration 172). */
final class MinuteProgramTrade
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        // Hex color (#rrggbb), stable per trade, see MinuteProgramTradeResolver::PALETTE.
        public readonly string $color,
    ) {
    }

    /** @return array{id: string, name: string, color: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'color' => $this->color];
    }
}
