<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Application\Command\CommandInterface;

final class CreateArticleSet implements CommandInterface
{
    /**
     * @param array<int, array<string, mixed>> $items rohe Zeilen [article_id, quantity]
     * @param array<int, array<string, mixed>> $rules rohe Zeilen [type, from_month_day, to_month_day, from_date, to_date]
     * @param array<int, array<string, mixed>> $upgrades rohe Zeilen [id, name, price, items[]]
     */
    public function __construct(
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
