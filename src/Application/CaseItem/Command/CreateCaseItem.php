<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandInterface;

final class CreateCaseItem implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $manufacturerId,
        public readonly string $prefix,
        public readonly ?string $description,
    ) {
    }
}
