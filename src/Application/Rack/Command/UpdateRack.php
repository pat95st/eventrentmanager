<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandInterface;

final class UpdateRack implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly RackInput $input,
    ) {
    }
}
