<?php

declare(strict_types=1);

namespace App\Domain\ClosingTextTemplate;

interface ClosingTextTemplateFolderRepositoryInterface
{
    public function save(ClosingTextTemplateFolder $folder): void;

    public function findById(string $id): ?ClosingTextTemplateFolder;

    /** @return ClosingTextTemplateFolder[] */
    public function findAll(): array;

    /** @return ClosingTextTemplateFolder[] Direkte Kindordner von $parentId (null = Wurzelebene) */
    public function findChildren(?string $parentId): array;

    /** @return string[] IDs aller (rekursiven) Nachfahren von $folderId */
    public function findDescendantIds(string $folderId): array;

    public function existsByNameAndParent(string $name, ?string $parentId, ?string $excludeId = null): bool;

    public function delete(string $id): void;
}
