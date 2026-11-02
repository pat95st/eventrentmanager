<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandInterface;

/** Foto des befüllten Case-Exemplars hochladen (Migration 114). */
final class AddCaseInventoryNumberImage implements CommandInterface
{
    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile */
    public function __construct(
        public readonly string $caseInventoryNumberId,
        public readonly array $uploadedFile,
    ) {
    }
}
