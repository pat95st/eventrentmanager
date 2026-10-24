<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandInterface;

final class AddCaseInventoryNumber implements CommandInterface
{
    public function __construct(
        public readonly string $caseId,
        public readonly ?string $acquisitionDate, // 'Y-m-d' oder null
    ) {
    }
}
