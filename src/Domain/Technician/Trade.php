<?php

declare(strict_types=1);

namespace App\Domain\Technician;

use DateTimeImmutable;

/** Gewerk, z. B. Ton, Licht, Video, Rigging (Migration 134). */
final class Trade
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public int $sortOrder,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
