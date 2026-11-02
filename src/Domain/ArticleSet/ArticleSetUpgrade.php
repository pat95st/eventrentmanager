<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

/** Upgrade-Option eines Sets: fester Aufpreis für zusätzliche Artikel. */
final class ArticleSetUpgrade
{
    /** @param ArticleSetUpgradeItem[] $items */
    public function __construct(
        public readonly string $id,
        public readonly string $setId,
        public string $name,
        public string $price,
        public int $sortOrder = 0,
        public array $items = [],
    ) {
    }
}
