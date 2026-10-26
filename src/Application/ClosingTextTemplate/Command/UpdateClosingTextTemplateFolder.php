<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandInterface;

final class UpdateClosingTextTemplateFolder implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $parentId,
    ) {
    }
}
