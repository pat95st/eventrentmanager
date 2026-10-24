<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

/** Zusammenfassung eines Import-Durchlaufs, siehe ImportReceiptEmailsHandler und bin/import-receipt-emails.php für die Ausgabe. */
final class ImportReceiptEmailsResult
{
    public function __construct(
        public readonly bool $disabled,
        public readonly int $importedMessages = 0,
        public readonly int $importedAttachments = 0,
        public readonly int $ignoredNotAllowed = 0,
        public readonly int $skippedDuplicates = 0,
        public readonly int $ignoredNoAttachment = 0,
    ) {
    }
}
