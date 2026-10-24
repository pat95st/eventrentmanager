<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\ReceiptInbox\ReceiptInboxAllowedSender;
use App\Domain\ReceiptInbox\ReceiptInboxAllowedSenderRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbReceiptInboxAllowedSenderRepository implements ReceiptInboxAllowedSenderRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(ReceiptInboxAllowedSender $sender): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO receipt_inbox_allowed_senders (id, email, note, created_at)
             VALUES (:id, :email, :note, :created_at)
             ON DUPLICATE KEY UPDATE email = :email2, note = :note2'
        );

        $stmt->execute([
            'id' => $sender->id,
            'email' => $sender->email,
            'note' => $sender->note,
            'created_at' => $sender->createdAt->format('Y-m-d H:i:s'),
            'email2' => $sender->email,
            'note2' => $sender->note,
        ]);
    }

    public function findById(string $id): ?ReceiptInboxAllowedSender
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipt_inbox_allowed_senders WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?ReceiptInboxAllowedSender
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipt_inbox_allowed_senders WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM receipt_inbox_allowed_senders ORDER BY email ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM receipt_inbox_allowed_senders WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): ReceiptInboxAllowedSender
    {
        return new ReceiptInboxAllowedSender(
            id: $row['id'],
            email: $row['email'],
            note: $row['note'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
