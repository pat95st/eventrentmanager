<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandInterface;

final class AddCaseContentItem implements CommandInterface
{
    public function __construct(
        public readonly string $caseInventoryNumberId,
        public readonly string $articleId,
        public readonly string $quantity, // als String aus dem Formular, wird im Handler geprüft/konvertiert
    ) {
    }
}
