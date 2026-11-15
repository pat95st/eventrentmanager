<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;

/** Exchanges the authorization code returned by the cloud service for tokens (OAuth callback). */
final class CompleteStorageTargetOAuth implements CommandInterface
{
    public function __construct(
        public readonly string $targetId,
        public readonly string $code,
        public readonly string $redirectUri,
    ) {
    }
}
