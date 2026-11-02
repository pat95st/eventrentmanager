<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Application\Command\CommandInterface;

final class UpdateArticleSet implements CommandInterface
{
    /** Siehe CreateArticleSet für das Format der Zeilen. */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $price,
        public readonly ?string $deploymentDays,
        public readonly bool $isActive,
        public readonly array $items,
        public readonly array $rules,
        public readonly array $upgrades,
    ) {
    }
}
