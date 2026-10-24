<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface FirmwareVersionRepositoryInterface
{
    public function save(FirmwareVersion $firmwareVersion): void;

    public function findById(string $id): ?FirmwareVersion;

    /** @return FirmwareVersion[] alle Versionen eines Artikels, neueste zuerst (nach releasedAt, dann createdAt) */
    public function findAllForArticle(string $articleId): array;

    /**
     * Die "aktuellste" Version eines Artikels, maßgeblich für den
     * Update-verfügbar-Vergleich (siehe ListFirmwareUpdatesAvailableHandler).
     * Sortierung: releasedAt (NULL zuletzt) desc, dann createdAt desc.
     */
    public function findLatestForArticle(string $articleId): ?FirmwareVersion;

    public function delete(string $id): void;
}
