<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;

final class CreateStorageAssignment implements CommandInterface
{
    public function __construct(
        public readonly string $documentType,
        public readonly string $storageTargetId,
        public readonly string $targetPath,
        public readonly bool $enabled,
    ) {
    }
}
