<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

/**
 * Löst für die Detailansichten/PDFs von Angebot, Auftragsbestätigung und
 * Rechnung die Bestandteile aller enthaltenen Bundles auf (Migration 107),
 * damit unter einer Bundle-Position "enthält 2× …, 1× …" angezeigt werden
 * kann.
 */
final class BundleComponentResolver
{
    public function __construct(
        private readonly ArticleBundleComponentRepositoryInterface $bundleComponents,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    /**
     * @param Article[] $articles beliebige Artikel; nur Bundles werden aufgelöst
     * @return array<string, list<array{articleId: string, label: string, quantity: int}>> bundleArticleId => Bestandteile
     */
    public function resolve(array $articles): array
    {
        $bundleIds = [];
        foreach ($articles as $article) {
            if ($article->isBundle) {
                $bundleIds[] = $article->id;
            }
        }
        if ($bundleIds === []) {
            return [];
        }

        $manufacturerCache = [];
        $result = [];
        foreach ($this->bundleComponents->findByBundles($bundleIds) as $bundleId => $components) {
            foreach ($components as $component) {
                $componentArticle = $this->articles->findById($component->componentArticleId);
                if ($componentArticle === null) {
                    continue;
                }
                $manufacturerId = ($componentArticle->manufacturerId ?? '');
                if (!array_key_exists($manufacturerId, $manufacturerCache)) {
                    $manufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
                }
                $result[$bundleId][] = [
                    'articleId' => $componentArticle->id,
                    'label' => ArticleDisplayName::of($componentArticle, $manufacturerCache[$manufacturerId]),
                    'quantity' => $component->quantity,
                ];
            }
        }

        return $result;
    }

    /**
     * Einzeilige Beschreibung für Anzeige/PDF, z. B. "enthält: 2× Lautsprecher X, 1× Endstufe Y".
     *
     * @param list<array{articleId: string, label: string, quantity: int}> $components
     */
    public static function describe(array $components, int $bundleQuantity = 1): string
    {
        $parts = array_map(static fn (array $c) => $c['quantity'] . '× ' . $c['label'], $components);

        return ($bundleQuantity > 1 ? 'enthält je Bundle: ' : 'enthält: ') . implode(', ', $parts);
    }
}
