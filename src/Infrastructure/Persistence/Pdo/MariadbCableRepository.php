<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\ProtectionClass;
use App\Domain\Cable\ArticleCable;
use App\Domain\Cable\CableRepositoryInterface;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;
use PDO;

/** Leitungen (Migration 125). */
final class MariadbCableRepository implements CableRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAllTypes(): array
    {
        $connectors = [];
        foreach ($this->pdo->query('SELECT cable_type_id, connector_type_id FROM cable_type_connectors')->fetchAll() as $row) {
            $connectors[$row['cable_type_id']][] = $row['connector_type_id'];
        }
        $rows = $this->pdo->query('SELECT * FROM cable_types ORDER BY sort_order, name')->fetchAll();

        return array_map(fn (array $row) => $this->hydrateType($row, $connectors[$row['id']] ?? []), $rows);
    }

    public function findTypeById(string $id): ?CableType
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cable_types WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT connector_type_id FROM cable_type_connectors WHERE cable_type_id = :id');
        $stmt->execute(['id' => $id]);

        return $this->hydrateType($row, array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function saveType(CableType $type): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cable_types (id, name, description, requires_electrical_inspection, protection_class, sort_order)
                 VALUES (:id, :name, :description, :requires, :protection_class, :sort_order)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                    requires_electrical_inspection = VALUES(requires_electrical_inspection),
                    protection_class = VALUES(protection_class), sort_order = VALUES(sort_order)'
            );
            $stmt->execute([
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'requires' => $type->requiresElectricalInspection ? 1 : 0,
                'protection_class' => $type->protectionClass?->value,
                'sort_order' => $type->sortOrder,
            ]);
            $this->pdo->prepare('DELETE FROM cable_type_connectors WHERE cable_type_id = :id')->execute(['id' => $type->id]);
            $insert = $this->pdo->prepare('INSERT INTO cable_type_connectors (cable_type_id, connector_type_id) VALUES (:type, :connector)');
            foreach (array_unique($type->connectorIds) as $connectorId) {
                $insert->execute(['type' => $type->id, 'connector' => $connectorId]);
            }
            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteType(string $id): void
    {
        $this->pdo->prepare('DELETE FROM cable_types WHERE id = :id')->execute(['id' => $id]);
    }

    public function typeNameExists(string $name, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cable_types WHERE name = :name AND id <> :exclude');
        $stmt->execute(['name' => $name, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findAllConnectors(): array
    {
        return array_map($this->hydrateConnector(...), $this->pdo->query('SELECT * FROM cable_connector_types ORDER BY sort_order, name')->fetchAll());
    }

    public function findConnectorById(string $id): ?ConnectorType
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cable_connector_types WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateConnector($row) : null;
    }

    public function saveConnector(ConnectorType $connector): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cable_connector_types (id, name, sort_order) VALUES (:id, :name, :sort_order)
             ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order)'
        );
        $stmt->execute(['id' => $connector->id, 'name' => $connector->name, 'sort_order' => $connector->sortOrder]);
    }

    public function deleteConnector(string $id): void
    {
        $this->pdo->prepare('DELETE FROM cable_connector_types WHERE id = :id')->execute(['id' => $id]);
    }

    public function connectorNameExists(string $name, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cable_connector_types WHERE name = :name AND id <> :exclude');
        $stmt->execute(['name' => $name, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function usageCounts(): array
    {
        $counts = [];
        $rows = $this->pdo->query(
            'SELECT cable_type_id AS id, COUNT(*) AS cnt FROM article_cables GROUP BY cable_type_id
             UNION ALL SELECT connector_a_id, COUNT(*) FROM article_cables GROUP BY connector_a_id
             UNION ALL SELECT connector_b_id, COUNT(*) FROM article_cables GROUP BY connector_b_id'
        )->fetchAll();
        foreach ($rows as $row) {
            $counts[$row['id']] = ($counts[$row['id']] ?? 0) + (int) $row['cnt'];
        }

        return $counts;
    }

    public function findByArticle(string $articleId): ?ArticleCable
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_cables WHERE article_id = :id');
        $stmt->execute(['id' => $articleId]);
        $row = $stmt->fetch();

        return $row ? new ArticleCable($row['article_id'], $row['cable_type_id'], (string) $row['length_m'], $row['connector_a_id'], $row['connector_b_id']) : null;
    }

    public function saveArticleCable(ArticleCable $cable): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_cables (article_id, cable_type_id, length_m, connector_a_id, connector_b_id)
             VALUES (:article_id, :cable_type_id, :length_m, :connector_a_id, :connector_b_id)
             ON DUPLICATE KEY UPDATE cable_type_id = VALUES(cable_type_id), length_m = VALUES(length_m),
                connector_a_id = VALUES(connector_a_id), connector_b_id = VALUES(connector_b_id)'
        );
        $stmt->execute([
            'article_id' => $cable->articleId,
            'cable_type_id' => $cable->cableTypeId,
            'length_m' => $cable->lengthM,
            'connector_a_id' => $cable->connectorAId,
            'connector_b_id' => $cable->connectorBId,
        ]);
    }

    public function deleteArticleCable(string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM article_cables WHERE article_id = :id')->execute(['id' => $articleId]);
    }

    /** @param string[] $connectorIds */
    private function hydrateType(array $row, array $connectorIds): CableType
    {
        return new CableType(
            id: $row['id'],
            name: $row['name'],
            description: $row['description'] ?? null,
            requiresElectricalInspection: (bool) $row['requires_electrical_inspection'],
            protectionClass: ProtectionClass::tryFrom((string) ($row['protection_class'] ?? '')),
            connectorIds: $connectorIds,
            sortOrder: (int) $row['sort_order'],
        );
    }

    private function hydrateConnector(array $row): ConnectorType
    {
        return new ConnectorType($row['id'], $row['name'], (int) $row['sort_order']);
    }
}
