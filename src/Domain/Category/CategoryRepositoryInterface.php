<?php

declare(strict_types=1);

namespace App\Domain\Category;

interface CategoryRepositoryInterface
{
    public function save(Category $category): void;

    public function findById(string $id): ?Category;

    /** @return Category[] */
    public function findAll(): array;

    /** @return Category[] Direkte Kindkategorien von $parentId (null = Wurzelebene) */
    public function findChildren(?string $parentId): array;

    /** @return string[] IDs aller (rekursiven) Nachfahren von $categoryId */
    public function findDescendantIds(string $categoryId): array;

    public function existsByNameAndParent(string $name, ?string $parentId, ?string $excludeId = null): bool;

    public function delete(string $id): void;
}
