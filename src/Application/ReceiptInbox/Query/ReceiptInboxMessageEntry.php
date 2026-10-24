<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Query;

use App\Domain\ReceiptInbox\ReceiptInboxAttachment;
use App\Domain\ReceiptInbox\ReceiptInboxMessage;

final class ReceiptInboxMessageEntry
{
    /** @param ReceiptInboxAttachment[] $attachments */
    public function __construct(
        public readonly ReceiptInboxMessage $message,
        public readonly array $attachments,
    ) {
    }
}
