<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Insurance\CurrentValue;
use App\Domain\Insurance\CurrentValueRepositoryInterface;
use App\Domain\Insurance\InsuranceField;
use DateTimeImmutable;
use PDO;

final class MariadbCurrentValueRepository implements CurrentValueRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forInventoryNumber(string $inventoryNumberId): ?CurrentValue
    {
        return $this->forInventoryNumbers([$inventoryNumberId])[$inventoryNumberId] ?? null;
    }

    public function forInventoryNumbers(array $inventoryNumberIds): array
    {
        $ids = array_values(array_unique($inventoryNumberIds));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT inventory_number_id, current_value, source, note, updated_at
             FROM inventory_number_current_values WHERE inventory_number_id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string) $row['inventory_number_id'];
            $result[$id] = new CurrentValue(
                inventoryNumberId: $id,
                value: (string) $row['current_value'],
                source: (string) $row['source'],
                note: $row['note'] !== null ? (string) $row['note'] : null,
                updatedAt: new DateTimeImmutable((string) $row['updated_at']),
            );
        }

        return $result;
    }

    public function setForInventoryNumber(string $inventoryNumberId, ?string $value, string $source = CurrentValue::SOURCE_MANUAL, ?string $note = null): void
    {
        if ($value === null) {
            $this->pdo->prepare('DELETE FROM inventory_number_current_values WHERE inventory_number_id = :id')
                ->execute(['id' => $inventoryNumberId]);

            return;
        }
        $this->pdo->prepare(
            'INSERT INTO inventory_number_current_values (inventory_number_id, current_value, source, note, updated_at)
             VALUES (:id, :value, :source, :note, NOW())
             ON DUPLICATE KEY UPDATE current_value = VALUES(current_value), source = VALUES(source), note = VALUES(note), updated_at = NOW()'
        )->execute([
            'id' => $inventoryNumberId,
            'value' => $value,
            'source' => $source,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);
    }

    public function articleFixedValue(string $articleId): ?string
    {
        return $this->articleFixedValues([$articleId])[$articleId] ?? null;
    }

    public function articleFixedValues(array $articleIds): array
    {
        $ids = array_values(array_unique($articleIds));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT article_id, current_value FROM article_fixed_current_values WHERE article_id IN ($placeholders)");
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string) $row['article_id']] = (string) $row['current_value'];
        }

        return $result;
    }

    public function setArticleFixedValue(string $articleId, ?string $value): void
    {
        if ($value === null) {
            $this->pdo->prepare('DELETE FROM article_fixed_current_values WHERE article_id = :id')->execute(['id' => $articleId]);

            return;
        }
        $this->pdo->prepare(
            'INSERT INTO article_fixed_current_values (article_id, current_value, updated_at) VALUES (:id, :value, NOW())
             ON DUPLICATE KEY UPDATE current_value = VALUES(current_value), updated_at = NOW()'
        )->execute(['id' => $articleId, 'value' => $value]);
    }

    public function exportFields(): array
    {
        $raw = $this->pdo->query('SELECT fields FROM insurance_export_settings WHERE id = 1')->fetchColumn();
        if ($raw === false) {
            return InsuranceField::cases();
        }

        return InsuranceField::fromList(explode(',', (string) $raw));
    }

    public function saveExportFields(array $fields): void
    {
        $value = implode(',', array_map(static fn (InsuranceField $f) => $f->value, $fields));
        $this->pdo->prepare(
            'INSERT INTO insurance_export_settings (id, fields) VALUES (1, :fields)
             ON DUPLICATE KEY UPDATE fields = VALUES(fields)'
        )->execute(['fields' => $value]);
    }
}
