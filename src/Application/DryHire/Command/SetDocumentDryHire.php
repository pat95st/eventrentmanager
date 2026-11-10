<?php

declare(strict_types=1);

namespace App\Application\DryHire\Command;

use App\Application\Command\CommandInterface;

/** Dry-Hire (Migration 141). */
final class SetDocumentDryHire implements CommandInterface
{
    public function __construct(
        public readonly string $documentType,
        public readonly string $documentId,
        public readonly bool $enabled,
    ) {
    }
}
