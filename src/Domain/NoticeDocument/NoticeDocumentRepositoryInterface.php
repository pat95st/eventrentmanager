<?php

declare(strict_types=1);

namespace App\Domain\NoticeDocument;

interface NoticeDocumentRepositoryInterface
{
    public function save(NoticeDocument $document): void;

    public function findById(string $id): ?NoticeDocument;

    /** @param string[] $ids */
    public function findByIds(array $ids): array;

    /** @return NoticeDocument[] sortiert nach Name aufsteigend */
    public function findAll(): array;

    public function delete(string $id): void;
}
