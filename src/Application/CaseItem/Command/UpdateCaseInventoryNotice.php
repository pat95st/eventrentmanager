<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandInterface;

/** Hinweistext (WYSIWYG, HTML) eines Case-Exemplars für die Stückliste (Migration 114). */
final class UpdateCaseInventoryNotice implements CommandInterface
{
    public function __construct(
        public readonly string $caseInventoryNumberId,
        public readonly ?string $noticeHtml,
    ) {
    }
}
