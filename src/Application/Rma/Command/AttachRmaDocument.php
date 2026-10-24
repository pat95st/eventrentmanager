<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandInterface;

final class AttachRmaDocument implements CommandInterface
{
    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile */
    public function __construct(
        public readonly string $rmaId,
        public readonly array $uploadedFile,
    ) {
    }
}
