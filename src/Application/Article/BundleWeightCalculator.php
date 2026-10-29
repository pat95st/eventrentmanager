<?php

declare(strict_types=1);

namespace App\Application\Article;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleBundleComponent;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\WeightConverter;

/**
 * Gewicht eines Bundles = Summe (Menge × Gewicht) seiner Bestandteile
 * (Hauptartikel, nicht die Alternativen), in kg (Migration 111). Wird beim
 * Speichern eines Bundles und beim Ändern eines Bestandteils neu berechnet
 * und auf dem Bundle-Artikel gespeichert, damit Listen, API und
 * Ladelisten ohne Sonderfall damit arbeiten.
 */
final class BundleWeightCalculator
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ?ArticleBundleComponentRepositoryInterface $bundleComponents = null,
    ) {
    }

    /**
     * @param ArticleBundleComponent[] $components
     * @return array{0: string|null, 1: string|null} [weightValue, weightUnit]; [null, null] wenn kein Bestandteil ein Gewicht hat
     */
    public function calculate(array $components): array
    {
        $totalKg = null;
        foreach ($components as $component) {
            $article = $this->articles->findById($component->componentArticleId);
            $kg = $article !== null ? WeightConverter::toKg($article->weightValue, $article->weightUnit) : null;
            if ($kg !== null) {
                $totalKg = ($totalKg ?? 0.0) + $kg * $component->quantity;
            }
        }

        if ($totalKg === null) {
            return [null, null];
        }

        return [number_format($totalKg, 3, '.', ''), 'kg'];
    }

    /** Setzt das berechnete Gewicht auf dem Bundle (ohne zu speichern). */
    public function apply(Article $bundle, array $components): void
    {
        [$bundle->weightValue, $bundle->weightUnit] = $this->calculate($components);
    }

    /** Rechnet alle Bundles neu, die den Artikel als Bestandteil enthalten, und speichert sie. */
    public function recalculateBundlesContaining(string $articleId): void
    {
        if ($this->bundleComponents === null) {
            return;
        }
        foreach ($this->bundleComponents->findBundleIdsContaining($articleId) as $bundleId) {
            $bundle = $this->articles->findById($bundleId);
            if ($bundle === null || !$bundle->isBundle) {
                continue;
            }
            $before = [$bundle->weightValue, $bundle->weightUnit];
            $this->apply($bundle, $this->bundleComponents->findByBundle($bundleId));
            if ($before !== [$bundle->weightValue, $bundle->weightUnit]) {
                $this->articles->save($bundle);
            }
        }
    }
}
