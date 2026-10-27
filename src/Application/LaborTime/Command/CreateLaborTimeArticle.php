<?php

declare(strict_types=1);

namespace App\Application\LaborTime\Command;

use App\Application\Command\CommandInterface;

final class CreateLaborTimeArticle implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $hourlyRate,
        // Optionaler Tagessatz, leerer String = kein Tagessatz (siehe
        // LaborTimeArticle::$dailyRate).
        public readonly string $dailyRate = '',
    ) {
    }
}
