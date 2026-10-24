<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\RentalCustomerChecklistToken;
use App\Domain\Rental\RentalCustomerChecklistTokenRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalCustomerChecklistTokenRepository implements RentalCustomerChecklistTokenRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(RentalCustomerChecklistToken $token): void
    {
        // Echtes Upsert über den UNIQUE-Constraint auf rental_id (siehe
        // Migration 071): "Link erneuern" ersetzt die bestehende Zeile
        // derselben Vermietung, statt eine zweite anzulegen.
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_customer_checklist_tokens (id, rental_id, token, created_at)
             VALUES (:id, :rental_id, :token, :created_at)
             ON DUPLICATE KEY UPDATE token = :token2, created_at = :created_at2'
        );

        $stmt->execute([
            'id' => $token->id,
            'rental_id' => $token->rentalId,
            'token' => $token->token,
            'created_at' => $token->createdAt->format('Y-m-d H:i:s'),
            'token2' => $token->token,
            'created_at2' => $token->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByRentalId(string $rentalId): ?RentalCustomerChecklistToken
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_customer_checklist_tokens WHERE rental_id = :rental_id');
        $stmt->execute(['rental_id' => $rentalId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByToken(string $token): ?RentalCustomerChecklistToken
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_customer_checklist_tokens WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): RentalCustomerChecklistToken
    {
        return new RentalCustomerChecklistToken(
            id: $row['id'],
            rentalId: $row['rental_id'],
            token: $row['token'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
