<?php

declare(strict_types=1);

namespace App\Domain\ReceiptInbox;

use DateTimeImmutable;

final class ReceiptInboxAttachment
{
    public function __construct(
        public readonly string $id,
        public readonly string $messageId,
        public readonly string $originalFilename,
        public readonly string $storedFilename, // Dateiname auf der Platte, unter storage/uploads/receipt-inbox/
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        // NULL = noch nicht zu einem Beleg umgewandelt, siehe
        // ConvertReceiptInboxAttachmentToReceiptHandler.
        public ?string $convertedReceiptId,
        public readonly DateTimeImmutable $createdAt,
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

    public function isConverted(): bool
    {
        return $this->convertedReceiptId !== null;
    }
}
