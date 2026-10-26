<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandInterface;

final class DeleteClosingTextTemplateFolder implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
