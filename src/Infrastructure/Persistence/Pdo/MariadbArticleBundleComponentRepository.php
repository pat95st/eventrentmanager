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

        $rows = $stmt->fetchAll();
        $alternatives = $this->loadAlternatives(array_column($rows, 'id'));

        $result = [];
        foreach ($rows as $row) {
            $component = $this->hydrate($row);
            $component->alternativeArticleIds = $alternatives[$component->id] ?? [];
            $result[$row['bundle_article_id']][] = $component;
        }

        return $result;
    }

    /**
     * @param string[] $componentIds
     * @return array<string, list<string>> componentId => Alternativartikel-IDs
     */
    private function loadAlternatives(array $componentIds): array
    {
        if ($componentIds === []) {
            return [];
        }
        $placeholders = [];
        $params = [];
        foreach (array_values($componentIds) as $i => $id) {
            $placeholders[] = ':c' . $i;
            $params['c' . $i] = $id;
        }
        $stmt = $this->pdo->prepare(
            'SELECT component_id, article_id FROM article_bundle_component_alternatives WHERE component_id IN (' . implode(', ', $placeholders) . ') ORDER BY sort_order ASC'
        );
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['component_id']][] = (string) $row['article_id'];
        }

        return $result;
    }

    public function findBundleIdsWithAlternative(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT c.bundle_article_id FROM article_bundle_component_alternatives a
             JOIN article_bundle_components c ON c.id = a.component_id
             WHERE a.article_id = :id'
        );
        $stmt->execute(['id' => $articleId]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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
            // Alternativen hängen per ON DELETE CASCADE an den Bestandteilen
            // und sind mit dem DELETE oben bereits entfernt.
            $insertAlternative = $this->pdo->prepare(
                'INSERT INTO article_bundle_component_alternatives (id, component_id, article_id, sort_order)
                 VALUES (:id, :component_id, :article_id, :sort_order)'
            );
            foreach ($components as $component) {
                $insert->execute([
                    'id' => $component->id,
                    'bundle_article_id' => $bundleArticleId,
                    'component_article_id' => $component->componentArticleId,
                    'quantity' => $component->quantity,
                    'sort_order' => $component->sortOrder,
                ]);
                foreach (array_values($component->alternativeArticleIds) as $position => $alternativeArticleId) {
                    $insertAlternative->execute([
                        'id' => \App\Shared\Uuid::v4(),
                        'component_id' => $component->id,
                        'article_id' => $alternativeArticleId,
                        'sort_order' => $position,
                    ]);
                }
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
