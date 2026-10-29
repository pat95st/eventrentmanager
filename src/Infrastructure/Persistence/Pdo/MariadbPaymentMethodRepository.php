<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\PaymentMethod;
use App\Domain\Settings\PaymentMethodRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbPaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM payment_methods ORDER BY is_default DESC, name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?PaymentMethod
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_methods WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findDefault(): ?PaymentMethod
    {
        $stmt = $this->pdo->query('SELECT * FROM payment_methods ORDER BY is_default DESC, created_at ASC LIMIT 1');
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(PaymentMethod $method): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO payment_methods (id, name, payment_term_days, note_text, is_default, created_at, updated_at)
             VALUES (:id, :name, :payment_term_days, :note_text, :is_default, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), payment_term_days = VALUES(payment_term_days),
                 note_text = VALUES(note_text), is_default = VALUES(is_default), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $method->id,
            'name' => $method->name,
            'payment_term_days' => $method->paymentTermDays,
            'note_text' => $method->noteText,
            'is_default' => $method->isDefault ? 1 : 0,
            'created_at' => $method->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $method->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function markAsDefault(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE payment_methods SET is_default = (id = :id)');
        $stmt->execute(['id' => $id]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM payment_methods WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PaymentMethod
    {
        return new PaymentMethod(
            id: $row['id'],
            name: $row['name'],
            paymentTermDays: (int) $row['payment_term_days'],
            noteText: $row['note_text'],
            isDefault: (bool) $row['is_default'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
