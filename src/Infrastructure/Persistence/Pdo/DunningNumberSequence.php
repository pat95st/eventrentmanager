<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use PDO;

/**
 * Vergibt die fortlaufende Mahnungsnummer, z. B. "MAH-000123" — analog
 * InvoiceNumberSequence (siehe dort für die ausführliche Begründung von
 * SELECT ... FOR UPDATE innerhalb einer Transaktion). Der Aufrufer
 * (CreateDunningNoticeHandler) muss allocateNext() innerhalb derselben
 * Transaktion aufrufen, in der auch die Mahnung selbst gespeichert wird.
 */
final class DunningNumberSequence
{
    private const PREFIX = 'MAH-';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allocateNext(): string
    {
        $select = $this->pdo->prepare('SELECT next_number FROM dunning_sequences WHERE id = 1 FOR UPDATE');
        $select->execute();
        $nextNumber = (int) $select->fetchColumn();

        $update = $this->pdo->prepare('UPDATE dunning_sequences SET next_number = :next WHERE id = 1');
        $update->execute(['next' => $nextNumber + 1]);

        return self::PREFIX . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
