<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandInterface;

final class UpdateDunningLevel implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $daysAfterDue,
        public readonly string $feeType,
        public readonly string $feeValue,
    ) {
    }
}
