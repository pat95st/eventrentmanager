<?php

declare(strict_types=1);

namespace App\Domain\Rma;

interface RmaDocumentRepositoryInterface
{
    public function add(RmaDocument $document): void;

    public function findById(string $id): ?RmaDocument;

    /** @return RmaDocument[] */
    public function findByRma(string $rmaId): array;

    /** @return RmaDocument[] Alle Dokumente aller RMAs einer Inventurnummer (für die Übersicht) */
    public function findByInventoryNumber(string $inventoryNumberId): array;

    public function delete(string $id): void;
}
