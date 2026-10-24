<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandInterface;

/** Wandelt einen Posteingang-Anhang in einen "echten" Beleg um — dieselben Felder wie CreateReceipt, aber OHNE Datei-Upload (die Datei liegt bereits im Posteingang). */
final class ConvertReceiptInboxAttachmentToReceipt implements CommandInterface
{
    public function __construct(
        public readonly string $attachmentId,
        public readonly string $supplierId,
        public readonly string $receiptNumber,
        public readonly string $rawAmount,
        public readonly string $rawReceiptDate,
        public readonly string $category,
        public readonly string $note,
    ) {
    }
}
