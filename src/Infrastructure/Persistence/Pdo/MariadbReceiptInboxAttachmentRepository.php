<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ReceiptInbox\ReceiptInboxAttachment;
use App\Domain\ReceiptInbox\ReceiptInboxAttachmentRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbReceiptInboxAttachmentRepository implements ReceiptInboxAttachmentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ReceiptInboxAttachment $attachment): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO receipt_inbox_attachments (
                id, message_id, original_filename, stored_filename, mime_type, size_bytes, converted_receipt_id, created_at
             ) VALUES (
                :id, :message_id, :original_filename, :stored_filename, :mime_type, :size_bytes, :converted_receipt_id, :created_at
             )
             ON DUPLICATE KEY UPDATE converted_receipt_id = :converted_receipt_id2'
        );

        $stmt->execute([
            'id' => $attachment->id,
            'message_id' => $attachment->messageId,
            'original_filename' => $attachment->originalFilename,
            'stored_filename' => $attachment->storedFilename,
            'mime_type' => $attachment->mimeType,
            'size_bytes' => $attachment->sizeBytes,
            'converted_receipt_id' => $attachment->convertedReceiptId,
            'created_at' => $attachment->createdAt->format('Y-m-d H:i:s'),
            'converted_receipt_id2' => $attachment->convertedReceiptId,
        ]);
    }

    public function findById(string $id): ?ReceiptInboxAttachment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipt_inbox_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByMessage(string $messageId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipt_inbox_attachments WHERE message_id = :message_id ORDER BY created_at ASC');
        $stmt->execute(['message_id' => $messageId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByMessages(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM receipt_inbox_attachments WHERE message_id IN ({$placeholders}) ORDER BY created_at ASC"
        );
        $stmt->execute(array_values($messageIds));

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $attachment = $this->hydrate($row);
            $grouped[$attachment->messageId][] = $attachment;
        }

        return $grouped;
    }

    public function countPending(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM receipt_inbox_attachments WHERE converted_receipt_id IS NULL');

        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): ReceiptInboxAttachment
    {
        return new ReceiptInboxAttachment(
            id: $row['id'],
            messageId: $row['message_id'],
            originalFilename: $row['original_filename'],
            storedFilename: $row['stored_filename'],
            mimeType: $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            convertedReceiptId: $row['converted_receipt_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
