<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Gewerk anlegen (id null) oder umbenennen (Migration 134). */
final class SaveTrade implements CommandInterface
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $sortOrder = '0',
    ) {
    }
}
