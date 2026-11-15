<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Command;

use App\Application\Command\CommandInterface;

final class DeleteDigitalProtocol implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
