<?php

declare(strict_types=1);

namespace App\Application\Cable\Command;

use App\Application\Command\CommandInterface;

/** Leitungstyp anlegen (id null) oder ändern. */
final class SaveCableType implements CommandInterface
{
    /** @param string[] $connectorIds */
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $requiresElectricalInspection,
        public readonly ?string $protectionClass,
        public readonly array $connectorIds,
        public readonly ?string $sortOrder = null,
    ) {
    }
}
