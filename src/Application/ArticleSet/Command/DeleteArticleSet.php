<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Application\Command\CommandInterface;

final class DeleteArticleSet implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
