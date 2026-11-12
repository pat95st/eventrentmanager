<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Depreciation\DepreciationAsset;
use App\Domain\Depreciation\DepreciationFirstYearMode;
use App\Domain\Depreciation\DepreciationRepositoryInterface;
use App\Domain\Depreciation\DepreciationSettings;
use DateTimeImmutable;
use PDO;

final class MariadbDepreciationRepository implements DepreciationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getSettings(): DepreciationSettings
    {
        $row = $this->pdo->query('SELECT * FROM depreciation_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return DepreciationSettings::defaults();
        }

        return new DepreciationSettings(
            defaultUsefulLifeYears: (int) $row['default_useful_life_years'],
            lowValueThreshold: (string) $row['low_value_threshold'],
            firstYearMode: DepreciationFirstYearMode::tryFrom((string) $row['first_year_mode']) ?? DepreciationFirstYearMode::Monthly,
            residualValue: (string) $row['residual_value'],
        );
    }

    public function saveSettings(DepreciationSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO depreciation_settings (id, default_useful_life_years, low_value_threshold, first_year_mode, residual_value)
             VALUES (1, :years, :threshold, :mode, :residual)
             ON DUPLICATE KEY UPDATE default_useful_life_years = VALUES(default_useful_life_years),
                 low_value_threshold = VALUES(low_value_threshold), first_year_mode = VALUES(first_year_mode),
                 residual_value = VALUES(residual_value)'
        )->execute([
            'years' => $settings->defaultUsefulLifeYears,
            'threshold' => $settings->lowValueThreshold,
            'mode' => $settings->firstYearMode->value,
            'residual' => $settings->residualValue,
        ]);
    }

    public function categoryUsefulLives(): array
    {
        $result = [];
        foreach ($this->pdo->query('SELECT category_id, useful_life_years FROM depreciation_category_useful_lives')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string) $row['category_id']] = (int) $row['useful_life_years'];
        }

        return $result;
    }

    public function replaceCategoryUsefulLives(array $years): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM depreciation_category_useful_lives');
            $stmt = $this->pdo->prepare('INSERT INTO depreciation_category_useful_lives (category_id, useful_life_years) VALUES (:id, :years)');
            foreach ($years as $categoryId => $value) {
                $stmt->execute(['id' => $categoryId, 'years' => $value]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    public function findAssets(?string $inventoryNumberId = null): array
    {
        $sql = 'SELECT i.id, i.number, i.acquisition_date, COALESCE(i.purchase_price, a.purchase_price) AS price,
                       a.id AS article_id, a.name AS article_name, a.category_id,
                       m.name AS manufacturer_name, c.name AS category_name,
                       (SELECT MIN(s.sold_at) FROM sales s WHERE s.inventory_number_id = i.id) AS sold_at
                FROM inventory_numbers i
                JOIN articles a ON a.id = i.article_id
                LEFT JOIN manufacturers m ON m.id = a.manufacturer_id
                LEFT JOIN categories c ON c.id = a.category_id';
        $params = [];
        if ($inventoryNumberId !== null) {
            $sql .= ' WHERE i.id = :id';
            $params['id'] = $inventoryNumberId;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY i.number ASC');
        $stmt->execute($params);

        $assets = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $assets[] = new DepreciationAsset(
                inventoryNumberId: (string) $row['id'],
                number: (string) $row['number'],
                articleId: (string) $row['article_id'],
                articleName: (string) $row['article_name'],
                manufacturerName: $row['manufacturer_name'] !== null ? (string) $row['manufacturer_name'] : null,
                categoryId: $row['category_id'] !== null ? (string) $row['category_id'] : null,
                categoryName: $row['category_name'] !== null ? (string) $row['category_name'] : null,
                purchasePrice: $row['price'] !== null ? (string) $row['price'] : null,
                acquisitionDate: $row['acquisition_date'] !== null ? new DateTimeImmutable((string) $row['acquisition_date']) : null,
                soldAt: $row['sold_at'] !== null ? new DateTimeImmutable((string) $row['sold_at']) : null,
            );
        }

        return $assets;
    }
}
