<?php

declare(strict_types=1);

namespace App\Application\LaborTime\Command;

use App\Application\Command\CommandInterface;

final class CreateLaborTimeArticle implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $hourlyRate,
    ) {
    }
}
