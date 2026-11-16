<?php

declare(strict_types=1);

namespace App\Application\Display\Command;

use App\Application\Command\CommandInterface;

/**
 * Creates (id null) or updates a display device.
 *
 * $target: "auto" (current program of all events running today),
 * "rental:<id>" (current program of that event) or "program:<id>" (pinned
 * minute program). $options null keeps the defaults of the role (creation)
 * or the stored options (update).
 */
final class SaveDisplayClient implements CommandInterface
{
    /** @param array<string, mixed>|null $options */
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $role,
        public readonly bool $enabled = true,
        public readonly string $target = 'auto',
        public readonly ?string $room = null,
        public readonly ?array $options = null,
    ) {
    }
}
