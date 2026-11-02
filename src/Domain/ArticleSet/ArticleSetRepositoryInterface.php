<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

interface ArticleSetRepositoryInterface
{
    public function save(ArticleSet $set): void;

    public function findById(string $id): ?ArticleSet;

    /** @return ArticleSet[] nach Name sortiert */
    public function findAll(bool $onlyActive = false): array;

    public function delete(string $id): void;

    /** @return ArticleSetItem[] */
    public function findItems(string $setId): array;

    /** @param ArticleSetItem[] $items */
    public function replaceItems(string $setId, array $items): void;

    /** @return ArticleSetRule[] */
    public function findRules(string $setId): array;

    /** @param ArticleSetRule[] $rules */
    public function replaceRules(string $setId, array $rules): void;

    /** @return ArticleSetUpgrade[] inkl. Artikel */
    public function findUpgrades(string $setId): array;

    /** @param ArticleSetUpgrade[] $upgrades inkl. Artikel */
    public function replaceUpgrades(string $setId, array $upgrades): void;

    public function findUpgradeById(string $upgradeId): ?ArticleSetUpgrade;

    /** Wird der Artikel in einem Set oder Upgrade verwendet? */
    public function isArticleUsed(string $articleId): bool;
}
