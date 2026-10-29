<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface ArticleBundleComponentRepositoryInterface
{
    /** @return ArticleBundleComponent[] nach sortOrder */
    public function findByBundle(string $bundleArticleId): array;

    /**
     * @param string[] $bundleArticleIds
     * @return array<string, ArticleBundleComponent[]> bundleArticleId => Bestandteile
     */
    public function findByBundles(array $bundleArticleIds): array;

    /** @return string[] IDs aller Bundles, die $componentArticleId enthalten */
    public function findBundleIdsContaining(string $componentArticleId): array;

    /**
     * Ersetzt die komplette Bestandteil-Liste eines Bundles (Muster wie
     * CustomerContactRepositoryInterface::replaceForCustomer()).
     *
     * @param ArticleBundleComponent[] $components
     */
    public function replaceForBundle(string $bundleArticleId, array $components): void;

    /**
     * Bundles, in denen der Artikel als Alternative zu einem Bestandteil
     * eingetragen ist (Migration 111).
     *
     * @return string[]
     */
    public function findBundleIdsWithAlternative(string $articleId): array;
}
