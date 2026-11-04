<?php

declare(strict_types=1);

namespace App\Application\Cable\Command;

use App\Application\Command\CommandInterface;

/** Steckverbindertyp anlegen (id null) oder umbenennen; optional gleich bei Leitungstypen erlauben. */
final class SaveConnectorType implements CommandInterface
{
    /** @param string[] $allowForCableTypeIds */
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly ?string $sortOrder = null,
        public readonly array $allowForCableTypeIds = [],
    ) {
    }
}
