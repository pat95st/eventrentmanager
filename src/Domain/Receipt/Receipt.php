<?php

declare(strict_types=1);

namespace App\Domain\Receipt;

use DateTimeImmutable;

/**
 * Ein Beleg — eine eingehende Lieferantenrechnung/-quittung (PDF-Upload),
 * bewusst zwingend einem Lieferanten zugeordnet (siehe
 * database/migrations/035_add_receipts.sql). Deutlich einfacher als Invoice:
 * keine fortlaufende Nummerierung, keine PDF-Erzeugung durch die Anwendung,
 * kein Sperr-/Unveränderlichkeits-Workflow — status/paidAt sind eine rein
 * interne, jederzeit umschaltbare Markierung.
 */
final class Receipt
{
    public function __construct(
        public readonly string $id,
        public string $supplierId,
        public ?string $receiptNumber, // Beleg-/Rechnungsnummer DES LIEFERANTEN, freies Feld
        public string $amount, // als String geführt (siehe Article::$dailyRentalPrice)
        public DateTimeImmutable $receiptDate,
        public ReceiptCategory $category,
        public ReceiptStatus $status,
        public ?DateTimeImmutable $paidAt,
        public ?string $note,
        public string $originalFilename,
        public string $storedFilename, // Dateiname auf der Platte, unter storage/uploads/receipts/
        public string $mimeType,
        public int $sizeBytes,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function humanReadableSize(): string
    {
        $bytes = $this->sizeBytes;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
