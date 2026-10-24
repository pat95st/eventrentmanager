<?php

declare(strict_types=1);

namespace App\Domain\Rma;

interface RmaReceiptRepositoryInterface
{
    /** @param string[] $receiptIds Vollständiger Ersatz der bisherigen Verknüpfungen. */
    public function replaceForRma(string $rmaId, array $receiptIds): void;

    /** @return string[] Beleg-IDs, neueste Verknüpfung zuerst. */
    public function findReceiptIdsByRma(string $rmaId): array;
}
