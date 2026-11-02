<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

final class ArticleSetUpgradeItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $upgradeId,
        public readonly string $articleId,
        public int $quantity,
    ) {
    }
}
