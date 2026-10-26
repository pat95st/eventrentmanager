<?php

declare(strict_types=1);

namespace App\Domain\ClosingTextTemplate;

interface ClosingTextTemplateRepositoryInterface
{
    public function save(ClosingTextTemplate $template): void;

    public function findById(string $id): ?ClosingTextTemplate;

    /** @return ClosingTextTemplate[] Alle Vorlagen, alphabetisch sortiert. */
    public function findAll(): array;

    /** @return ClosingTextTemplate[] Vorlagen in $folderId (null = unsortiert/Wurzelebene), alphabetisch sortiert. */
    public function findByFolder(?string $folderId): array;

    public function countByFolder(string $folderId): int;

    public function delete(string $id): void;
}
