<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/**
 * Saves which confirmations Shift may skip (Einstellungen › Sicherheit,
 * administrators only, see route). $requiredKeys lists the confirmations that
 * must always be answered; $reset restores the catalog defaults instead.
 */
final class UpdateConfirmSettings implements CommandInterface
{
    /** @param list<string> $requiredKeys */
    public function __construct(
        public readonly bool $bypassEnabled,
        public readonly array $requiredKeys,
        public readonly bool $reset = false,
    ) {
    }
}
