<?php

declare(strict_types=1);

namespace App\Application\DryHire\Command;

use App\Application\Command\CommandInterface;

/** Dry-Hire (Migration 142). */
final class SaveDryHireTiers implements CommandInterface
{
    /** @param list<array{0: int|string, 1: string}> $tiers [ab Tag, Prozent] */
    public function __construct(
        public readonly array $tiers,
    ) {
    }
}
