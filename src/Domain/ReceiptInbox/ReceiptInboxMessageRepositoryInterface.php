<?php

declare(strict_types=1);

namespace App\Domain\ReceiptInbox;

interface ReceiptInboxMessageRepositoryInterface
{
    public function save(ReceiptInboxMessage $message): void;

    public function findById(string $id): ?ReceiptInboxMessage;

    public function findByMessageId(string $messageId): ?ReceiptInboxMessage;

    /** @return ReceiptInboxMessage[] Neueste zuerst. */
    public function findAll(int $limit, int $offset): array;

    public function countAll(): int;
}
