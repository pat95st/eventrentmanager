<?php

declare(strict_types=1);

namespace App\Application\DryHire\Command;

use App\Application\Command\CommandInterface;

/** Dry-Hire (Migration 142). */
final class SetArticleDryHirePrice implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $dailyPrice,
    ) {
    }
}
