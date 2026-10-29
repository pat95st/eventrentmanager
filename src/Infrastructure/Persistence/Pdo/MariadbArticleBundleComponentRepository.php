<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\ArticleBundleComponent;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use PDO;

final class MariadbArticleBundleComponentRepository implements ArticleBundleComponentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByBundle(string $bundleArticleId): array
    {
        return $this->findByBundles([$bundleArticleId])[$bundleArticleId] ?? [];
    }

    public function findByBundles(array $bundleArticleIds): array
    {
        $bundleArticleIds = array_values(array_unique($bundleArticleIds));
        if ($bundleArticleIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($bundleArticleIds as $i => $id) {
            $placeholders[] = ':b' . $i;
            $params['b' . $i] = $id;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM article_bundle_components WHERE bundle_article_id IN (' . implode(', ', $placeholders) . ') ORDER BY sort_order ASC'
        );
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['bundle_article_id']][] = $this->hydrate($row);
        }

        return $result;
    }

    public function findBundleIdsContaining(string $componentArticleId): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT bundle_article_id FROM article_bundle_components WHERE component_article_id = :id');
        $stmt->execute(['id' => $componentArticleId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function replaceForBundle(string $bundleArticleId, array $components): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $delete = $this->pdo->prepare('DELETE FROM article_bundle_components WHERE bundle_article_id = :id');
            $delete->execute(['id' => $bundleArticleId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO article_bundle_components (id, bundle_article_id, component_article_id, quantity, sort_order)
                 VALUES (:id, :bundle_article_id, :component_article_id, :quantity, :sort_order)'
            );
            foreach ($components as $component) {
                $insert->execute([
                    'id' => $component->id,
                    'bundle_article_id' => $bundleArticleId,
                    'component_article_id' => $component->componentArticleId,
                    'quantity' => $component->quantity,
                    'sort_order' => $component->sortOrder,
                ]);
            }

            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ArticleBundleComponent
    {
        return new ArticleBundleComponent(
            id: $row['id'],
            bundleArticleId: $row['bundle_article_id'],
            componentArticleId: $row['component_article_id'],
            quantity: (int) $row['quantity'],
            sortOrder: (int) $row['sort_order'],
        );
    }
}
