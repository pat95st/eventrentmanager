<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Query;

use App\Domain\ArticleSet\ArticleSet;
use App\Domain\ArticleSet\ArticleSetRule;

/** Set mit aufgelösten Artikel-Labels, Regeln und Upgrades. */
final class ArticleSetDetail
{
    /**
     * @param list<array{articleId: string, label: string, quantity: int, isBundle: bool}> $items
     * @param ArticleSetRule[] $rules
     * @param list<array{id: string, name: string, price: string, items: list<array{articleId: string, label: string, quantity: int}>}> $upgrades
     */
    public function __construct(
        public readonly ArticleSet $set,
        public readonly array $items,
        public readonly array $rules,
        public readonly array $upgrades,
    ) {
    }

    /** "2× Lautsprecher-Set, 1× Mischpult" */
    public function contentsDescription(): string
    {
        return self::describe($this->items);
    }

    /** @param list<array{label: string, quantity: int}> $items */
    public static function describe(array $items): string
    {
        return implode(', ', array_map(static fn (array $item) => $item['quantity'] . '× ' . $item['label'], $items));
    }

    /** "nur am Wochenende (Fr–Mo); jedes Jahr vom 01.05. bis 30.09." oder '' */
    public function rulesDescription(): string
    {
        return implode('; ', array_map(static fn (ArticleSetRule $rule) => $rule->describe(), $this->rules));
    }

    /** @return array{id: string, name: string, price: string, items: list<array{articleId: string, label: string, quantity: int}>}|null */
    public function upgrade(string $upgradeId): ?array
    {
        foreach ($this->upgrades as $upgrade) {
            if ($upgrade['id'] === $upgradeId) {
                return $upgrade;
            }
        }

        return null;
    }
}
