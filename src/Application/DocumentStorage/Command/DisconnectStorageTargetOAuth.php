<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;

/** Forgets the stored OAuth tokens of a storage target (client ID/secret stay). */
final class DisconnectStorageTargetOAuth implements CommandInterface
{
    public function __construct(public readonly string $targetId)
    {
    }
}
