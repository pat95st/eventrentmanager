<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ReceiptInbox\ReceiptInboxMessage;
use App\Domain\ReceiptInbox\ReceiptInboxMessageRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbReceiptInboxMessageRepository implements ReceiptInboxMessageRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ReceiptInboxMessage $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO receipt_inbox_messages (id, message_id, from_address, subject, received_at, imported_at)
             VALUES (:id, :message_id, :from_address, :subject, :received_at, :imported_at)'
        );

        $stmt->execute([
            'id' => $message->id,
            'message_id' => $message->messageId,
            'from_address' => $message->fromAddress,
            'subject' => $message->subject,
            'received_at' => $message->receivedAt?->format('Y-m-d H:i:s'),
            'imported_at' => $message->importedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?ReceiptInboxMessage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipt_inbox_messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByMessageId(string $messageId): ?ReceiptInboxMessage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipt_inbox_messages WHERE message_id = :message_id');
        $stmt->execute(['message_id' => $messageId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM receipt_inbox_messages ORDER BY COALESCE(received_at, imported_at) DESC, imported_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM receipt_inbox_messages');

        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): ReceiptInboxMessage
    {
        return new ReceiptInboxMessage(
            id: $row['id'],
            messageId: $row['message_id'],
            fromAddress: $row['from_address'],
            subject: $row['subject'],
            receivedAt: $row['received_at'] !== null ? new DateTimeImmutable($row['received_at']) : null,
            importedAt: new DateTimeImmutable($row['imported_at']),
        );
    }
}
