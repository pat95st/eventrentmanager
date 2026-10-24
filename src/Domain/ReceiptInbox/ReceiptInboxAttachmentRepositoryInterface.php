<?php

declare(strict_types=1);

namespace App\Domain\ReceiptInbox;

interface ReceiptInboxAttachmentRepositoryInterface
{
    public function save(ReceiptInboxAttachment $attachment): void;

    public function findById(string $id): ?ReceiptInboxAttachment;

    /** @return ReceiptInboxAttachment[] */
    public function findByMessage(string $messageId): array;

    /** @return array<string, ReceiptInboxAttachment[]> Anhänge gruppiert nach messageId, für die Posteingang-Übersicht. */
    public function findByMessages(array $messageIds): array;

    /** Anzahl der Anhänge, die noch nicht zu einem Beleg umgewandelt wurden (für das Badge im Menüband). */
    public function countPending(): int;
}
