<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbInventoryNumberRepository implements InventoryNumberRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(InventoryNumber $inventoryNumber): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventory_numbers (id, article_id, number, acquisition_date, supplier_id, receipt_id, purchase_price, storage_location, installed_firmware_version_id, current_value, current_value_source, current_value_note, current_value_updated_at, created_at, updated_at)
             VALUES (:id, :article_id, :number, :acquisition_date, :supplier_id, :receipt_id, :purchase_price, :storage_location, :installed_firmware_version_id, :current_value, :current_value_source, :current_value_note, :current_value_updated_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE number = :number2, acquisition_date = :acquisition_date2,
                supplier_id = :supplier_id2, receipt_id = :receipt_id2, purchase_price = :purchase_price2,
                storage_location = :storage_location2, installed_firmware_version_id = :installed_firmware_version_id2,
                current_value = :current_value2, current_value_source = :current_value_source2,
                current_value_note = :current_value_note2, current_value_updated_at = :current_value_updated_at2, updated_at = :updated_at2'
        );

        $acquisitionDate = $inventoryNumber->acquisitionDate?->format('Y-m-d');
        $currentValueUpdatedAt = $inventoryNumber->currentValueUpdatedAt?->format('Y-m-d H:i:s');

        $stmt->execute([
            'id' => $inventoryNumber->id,
            'article_id' => $inventoryNumber->articleId,
            'number' => $inventoryNumber->number,
            'acquisition_date' => $acquisitionDate,
            'supplier_id' => $inventoryNumber->supplierId,
            'receipt_id' => $inventoryNumber->receiptId,
            'purchase_price' => $inventoryNumber->purchasePriceOverride,
            'storage_location' => $inventoryNumber->storageLocation,
            'installed_firmware_version_id' => $inventoryNumber->installedFirmwareVersionId,
            'current_value' => $inventoryNumber->currentValue,
            'current_value_source' => $inventoryNumber->currentValueSource,
            'current_value_note' => $inventoryNumber->currentValueNote,
            'current_value_updated_at' => $currentValueUpdatedAt,
            'created_at' => $inventoryNumber->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $inventoryNumber->updatedAt->format('Y-m-d H:i:s'),
            'number2' => $inventoryNumber->number,
            'acquisition_date2' => $acquisitionDate,
            'supplier_id2' => $inventoryNumber->supplierId,
            'receipt_id2' => $inventoryNumber->receiptId,
            'purchase_price2' => $inventoryNumber->purchasePriceOverride,
            'storage_location2' => $inventoryNumber->storageLocation,
            'installed_firmware_version_id2' => $inventoryNumber->installedFirmwareVersionId,
            'current_value2' => $inventoryNumber->currentValue,
            'current_value_source2' => $inventoryNumber->currentValueSource,
            'current_value_note2' => $inventoryNumber->currentValueNote,
            'current_value_updated_at2' => $currentValueUpdatedAt,
            'updated_at2' => $inventoryNumber->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?InventoryNumber
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_numbers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByNumber(string $number): ?InventoryNumber
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inventory_numbers WHERE number = :number');
        $stmt->execute(['number' => $number]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inventory_numbers WHERE article_id = :article_id ORDER BY number ASC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countActiveByArticles(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_filter($articleIds)));
        if ($articleIds === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($articleIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT n.article_id, COUNT(*) AS cnt FROM inventory_numbers n
             LEFT JOIN sales s ON s.inventory_number_id = n.id
             WHERE n.article_id IN ($placeholders) AND s.id IS NULL
             GROUP BY n.article_id"
        );
        $stmt->execute($articleIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['article_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function findActiveByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.* FROM inventory_numbers n
             LEFT JOIN sales s ON s.inventory_number_id = n.id
             WHERE n.article_id = :article_id AND s.id IS NULL
             ORDER BY n.number ASC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByReceipt(string $receiptId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inventory_numbers WHERE receipt_id = :receipt_id ORDER BY number ASC'
        );
        $stmt->execute(['receipt_id' => $receiptId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsByNumber(string $number, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM inventory_numbers WHERE number = :number';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['number' => $number];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findAllWithInstalledFirmwareVersion(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inventory_numbers WHERE installed_firmware_version_id IS NOT NULL ORDER BY number ASC'
        );
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsByInstalledFirmwareVersion(string $firmwareVersionId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM inventory_numbers WHERE installed_firmware_version_id = :id');
        $stmt->execute(['id' => $firmwareVersionId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM inventory_numbers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): InventoryNumber
    {
        return new InventoryNumber(
            id: $row['id'],
            articleId: $row['article_id'],
            number: $row['number'],
            acquisitionDate: $row['acquisition_date'] !== null ? new DateTimeImmutable($row['acquisition_date']) : null,
            supplierId: $row['supplier_id'],
            receiptId: $row['receipt_id'],
            purchasePriceOverride: $row['purchase_price'],
            storageLocation: $row['storage_location'],
            installedFirmwareVersionId: $row['installed_firmware_version_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            currentValue: isset($row['current_value']) ? (string) $row['current_value'] : null,
            currentValueSource: isset($row['current_value_source']) ? (string) $row['current_value_source'] : null,
            currentValueNote: isset($row['current_value_note']) ? (string) $row['current_value_note'] : null,
            currentValueUpdatedAt: isset($row['current_value_updated_at']) ? new DateTimeImmutable((string) $row['current_value_updated_at']) : null,
        );
    }
}
