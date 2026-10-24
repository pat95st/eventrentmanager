<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandInterface;

final class DeleteCaseContentItem implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
