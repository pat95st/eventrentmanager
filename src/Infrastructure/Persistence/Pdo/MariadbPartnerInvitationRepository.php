<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Federation\PartnerInvitation;
use App\Domain\Federation\PartnerInvitationRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbPartnerInvitationRepository implements PartnerInvitationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PartnerInvitation $invitation): void
    {
        $this->pdo->prepare(
            'INSERT INTO partner_invitations (id, code_hash, note, expires_at, used_at, partner_id, created_at)
             VALUES (:id, :code_hash, :note, :expires_at, :used_at, :partner_id, :created_at)
             ON DUPLICATE KEY UPDATE note = VALUES(note), expires_at = VALUES(expires_at),
                used_at = VALUES(used_at), partner_id = VALUES(partner_id)',
        )->execute([
            'id' => $invitation->id,
            'code_hash' => $invitation->codeHash,
            'note' => $invitation->note,
            'expires_at' => $invitation->expiresAt->format('Y-m-d H:i:s'),
            'used_at' => $invitation->usedAt?->format('Y-m-d H:i:s'),
            'partner_id' => $invitation->partnerId,
            'created_at' => $invitation->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?PartnerInvitation
    {
        return $this->findOneBy('id', $id);
    }

    public function findByCodeHash(string $hash): ?PartnerInvitation
    {
        return $this->findOneBy('code_hash', $hash);
    }

    public function findOpen(): array
    {
        $rows = $this->pdo->query('SELECT * FROM partner_invitations WHERE used_at IS NULL ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn (array $row) => $this->hydrate($row), $rows);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM partner_invitations WHERE id = :id AND used_at IS NULL')->execute(['id' => $id]);
    }

    private function findOneBy(string $column, string $value): ?PartnerInvitation
    {
        $statement = $this->pdo->prepare("SELECT * FROM partner_invitations WHERE {$column} = :value LIMIT 1");
        $statement->execute(['value' => $value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PartnerInvitation
    {
        return new PartnerInvitation(
            id: (string) $row['id'],
            codeHash: (string) $row['code_hash'],
            note: $row['note'] !== null ? (string) $row['note'] : null,
            expiresAt: new DateTimeImmutable((string) $row['expires_at']),
            usedAt: $row['used_at'] !== null ? new DateTimeImmutable((string) $row['used_at']) : null,
            partnerId: $row['partner_id'] !== null ? (string) $row['partner_id'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
