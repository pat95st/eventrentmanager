<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\ProtectionClass;
use DateTimeImmutable;
use PDO;

final class MariadbArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Article $article): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO articles
                (id, name, manufacturer_id, prefix, category_id, description, weight_value, weight_unit, dimensions, is_19_inch, height_units, install_depth_cm, purchase_price, requires_electrical_inspection, protection_class, has_firmware, is_billable, is_rentable_individually, is_bundle, is_active, daily_rental_price, created_at, updated_at)
             VALUES
                (:id, :name, :manufacturer_id, :prefix, :category_id, :description, :weight_value, :weight_unit, :dimensions, :is_19_inch, :height_units, :install_depth_cm, :purchase_price, :requires_electrical_inspection, :protection_class, :has_firmware, :is_billable, :is_rentable_individually, :is_bundle, :is_active, :daily_rental_price, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                name = :name2, manufacturer_id = :manufacturer_id2, prefix = :prefix2, category_id = :category_id2,
                description = :description2, weight_value = :weight_value2, weight_unit = :weight_unit2,
                dimensions = :dimensions2, is_19_inch = :is_19_inch2, height_units = :height_units2,
                install_depth_cm = :install_depth_cm2, purchase_price = :purchase_price2,
                requires_electrical_inspection = :requires_electrical_inspection2, protection_class = :protection_class2,
                has_firmware = :has_firmware2,
                is_billable = :is_billable2, is_rentable_individually = :is_rentable_individually2, is_bundle = :is_bundle2, is_active = :is_active2, daily_rental_price = :daily_rental_price2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $article->id,
            'name' => $article->name,
            'manufacturer_id' => $article->manufacturerId,
            'prefix' => $article->prefix,
            'category_id' => $article->categoryId,
            'description' => $article->description,
            'weight_value' => $article->weightValue,
            'weight_unit' => $article->weightUnit,
            'dimensions' => $article->dimensions,
            'is_19_inch' => $article->is19Inch ? 1 : 0,
            'height_units' => $article->heightUnits,
            'install_depth_cm' => $article->installDepthCm,
            'purchase_price' => $article->purchasePrice,
            'requires_electrical_inspection' => $article->requiresElectricalInspection ? 1 : 0,
            'protection_class' => $article->protectionClass?->value,
            'has_firmware' => $article->hasFirmware ? 1 : 0,
            'is_billable' => $article->isBillable ? 1 : 0,
            'is_rentable_individually' => $article->isRentableIndividually ? 1 : 0,
            'is_bundle' => $article->isBundle ? 1 : 0,
            'is_active' => $article->isActive ? 1 : 0,
            'daily_rental_price' => $article->dailyRentalPrice,
            'created_at' => $article->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $article->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $article->name,
            'manufacturer_id2' => $article->manufacturerId,
            'prefix2' => $article->prefix,
            'category_id2' => $article->categoryId,
            'description2' => $article->description,
            'weight_value2' => $article->weightValue,
            'weight_unit2' => $article->weightUnit,
            'dimensions2' => $article->dimensions,
            'is_19_inch2' => $article->is19Inch ? 1 : 0,
            'height_units2' => $article->heightUnits,
            'install_depth_cm2' => $article->installDepthCm,
            'purchase_price2' => $article->purchasePrice,
            'requires_electrical_inspection2' => $article->requiresElectricalInspection ? 1 : 0,
            'protection_class2' => $article->protectionClass?->value,
            'has_firmware2' => $article->hasFirmware ? 1 : 0,
            'is_billable2' => $article->isBillable ? 1 : 0,
            'is_rentable_individually2' => $article->isRentableIndividually ? 1 : 0,
            'is_bundle2' => $article->isBundle ? 1 : 0,
            'is_active2' => $article->isActive ? 1 : 0,
            'daily_rental_price2' => $article->dailyRentalPrice,
            'updated_at2' => $article->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Article
    {
        $stmt = $this->pdo->prepare('SELECT * FROM articles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return $this->hydrate($row, $this->loadImages($id));
    }

    public function search(?string $searchTerm, ?string $manufacturerId, array $categoryIds, int $limit, int $offset, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $manufacturerId, $categoryIds, $onlyActive, $manufacturerIds, $onlyRentableIndividually, $excludeBundles);

        $sql = 'SELECT * FROM articles' . $where . ' ORDER BY name ASC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if ($rows === []) {
            return [];
        }

        $articleIds = array_column($rows, 'id');
        $imagesByArticle = $this->loadImagesForArticles($articleIds);

        return array_map(
            fn (array $row) => $this->hydrate($row, $imagesByArticle[$row['id']] ?? []),
            $rows
        );
    }

    public function countSearch(?string $searchTerm, ?string $manufacturerId, array $categoryIds, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $manufacturerId, $categoryIds, $onlyActive, $manufacturerIds, $onlyRentableIndividually, $excludeBundles);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM articles' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param string[] $categoryIds
     * @param string[] $manufacturerIds
     * @return array{0: string, 1: array<string, string>} [WHERE-Klausel (inkl. führendem Leerzeichen), Parameter]
     */
    private function buildSearchWhere(?string $searchTerm, ?string $manufacturerId, array $categoryIds, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }

        // Bundles (Migration 107), siehe ListArticles::$onlyRentableIndividually.
        if ($onlyRentableIndividually) {
            $sql .= ' AND is_rentable_individually = 1';
        }
        if ($excludeBundles) {
            $sql .= ' AND is_bundle = 0';
        }

        if ($searchTerm !== null && $searchTerm !== '') {
            $sql .= ' AND (name LIKE :term OR description LIKE :term)';
            $params['term'] = '%' . $searchTerm . '%';
        }

        if ($manufacturerId !== null) {
            $sql .= ' AND manufacturer_id = :manufacturer_id';
            $params['manufacturer_id'] = $manufacturerId;
        }

        if ($manufacturerIds !== []) {
            $placeholders = [];
            foreach (array_values($manufacturerIds) as $i => $id) {
                $key = "man{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $id;
            }
            $sql .= ' AND manufacturer_id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($categoryIds !== []) {
            $placeholders = [];
            foreach ($categoryIds as $i => $categoryId) {
                $key = "cat{$i}";
                $placeholders[] = ":{$key}";
                $params[$key] = $categoryId;
            }
            $sql .= ' AND category_id IN (' . implode(', ', $placeholders) . ')';
        }

        return [$sql, $params];
    }

    public function countByCategory(string $categoryId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM articles WHERE category_id = :category_id');
        $stmt->execute(['category_id' => $categoryId]);

        return (int) $stmt->fetchColumn();
    }

    public function countByManufacturer(string $manufacturerId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM articles WHERE manufacturer_id = :manufacturer_id');
        $stmt->execute(['manufacturer_id' => $manufacturerId]);

        return (int) $stmt->fetchColumn();
    }

    public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM articles WHERE manufacturer_id = :manufacturer_id AND prefix = :prefix';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['manufacturer_id' => $manufacturerId, 'prefix' => $prefix];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM articles WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function addImage(ArticleImage $image): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_images (id, article_id, file_path, position, created_at)
             VALUES (:id, :article_id, :file_path, :position, :created_at)'
        );

        $stmt->execute([
            'id' => $image->id,
            'article_id' => $image->articleId,
            'file_path' => $image->filePath,
            'position' => $image->position,
            'created_at' => $image->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteImage(string $imageId): ?ArticleImage
    {
        $image = $this->findImageById($imageId);
        if ($image === null) {
            return null;
        }

        $stmt = $this->pdo->prepare('DELETE FROM article_images WHERE id = :id');
        $stmt->execute(['id' => $imageId]);

        return $image;
    }

    public function findImageById(string $imageId): ?ArticleImage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_images WHERE id = :id');
        $stmt->execute(['id' => $imageId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateImage($row) : null;
    }

    /** @return ArticleImage[] */
    private function loadImages(string $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM article_images WHERE article_id = :article_id ORDER BY position ASC, created_at ASC'
        );
        $stmt->execute(['article_id' => $articleId]);

        return array_map($this->hydrateImage(...), $stmt->fetchAll());
    }

    /**
     * @param string[] $articleIds
     * @return array<string, ArticleImage[]>
     */
    private function loadImagesForArticles(array $articleIds): array
    {
        $placeholders = [];
        $params = [];
        foreach ($articleIds as $i => $id) {
            $key = "id{$i}";
            $placeholders[] = ":{$key}";
            $params[$key] = $id;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM article_images WHERE article_id IN (' . implode(', ', $placeholders) . ')
             ORDER BY position ASC, created_at ASC'
        );
        $stmt->execute($params);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[$row['article_id']][] = $this->hydrateImage($row);
        }

        return $grouped;
    }

    private function hydrateImage(array $row): ArticleImage
    {
        return new ArticleImage(
            id: $row['id'],
            articleId: $row['article_id'],
            filePath: $row['file_path'],
            position: (int) $row['position'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }

    /** @param ArticleImage[] $images */
    private function hydrate(array $row, array $images): Article
    {
        return new Article(
            id: $row['id'],
            name: $row['name'],
            manufacturerId: $row['manufacturer_id'],
            prefix: $row['prefix'],
            categoryId: $row['category_id'],
            description: $row['description'],
            weightValue: $row['weight_value'],
            weightUnit: $row['weight_unit'],
            dimensions: $row['dimensions'],
            is19Inch: (bool) $row['is_19_inch'],
            heightUnits: $row['height_units'] !== null ? (int) $row['height_units'] : null,
            installDepthCm: $row['install_depth_cm'],
            purchasePrice: $row['purchase_price'],
            requiresElectricalInspection: (bool) $row['requires_electrical_inspection'],
            protectionClass: $row['protection_class'] !== null ? ProtectionClass::from($row['protection_class']) : null,
            hasFirmware: (bool) $row['has_firmware'],
            isBillable: (bool) $row['is_billable'],
            isActive: (bool) $row['is_active'],
            dailyRentalPrice: $row['daily_rental_price'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            images: $images,
            isRentableIndividually: (bool) ($row['is_rentable_individually'] ?? true),
            isBundle: (bool) ($row['is_bundle'] ?? false),
        );
    }
}
