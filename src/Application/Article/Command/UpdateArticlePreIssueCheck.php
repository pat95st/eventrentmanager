<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class UpdateArticlePreIssueCheck implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
    ) {
    }
}
