<?php

declare(strict_types=1);

namespace App\Domain\ReceiptInbox;

interface ReceiptInboxAllowedSenderRepositoryInterface
{
    public function save(ReceiptInboxAllowedSender $sender): void;

    public function findById(string $id): ?ReceiptInboxAllowedSender;

    public function findByEmail(string $email): ?ReceiptInboxAllowedSender;

    /** @return ReceiptInboxAllowedSender[] */
    public function findAll(): array;

    public function delete(string $id): void;
}
