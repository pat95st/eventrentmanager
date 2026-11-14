<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Command;

use App\Application\Command\CommandInterface;

/** Digitales Protokoll anlegen (id null) oder umbenennen. */
final class SaveDigitalProtocol implements CommandInterface
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly ?string $sortOrder = null,
    ) {
    }
}
