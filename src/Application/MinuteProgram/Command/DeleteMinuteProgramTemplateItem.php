<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class DeleteMinuteProgramTemplateItem implements CommandInterface
{
    public function __construct(public readonly string $itemId)
    {
    }
}
