<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\MinuteProgram\MinuteProgramShareLink;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;
use App\Domain\MinuteProgram\ShareLinkMode;
use DateTimeImmutable;
use PDO;

final class MariadbMinuteProgramShareLinkRepository implements MinuteProgramShareLinkRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(MinuteProgramShareLink $link): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO minute_program_share_links (
                id, minute_program_id, token, mode, created_at, revoked_at
             ) VALUES (
                :id, :minute_program_id, :token, :mode, :created_at, :revoked_at
             )
             ON DUPLICATE KEY UPDATE revoked_at = :revoked_at2'
        );

        $stmt->execute([
            'id' => $link->id,
            'minute_program_id' => $link->minuteProgramId,
            'token' => $link->token,
            'mode' => $link->mode->value,
            'created_at' => $link->createdAt->format('Y-m-d H:i:s'),
            'revoked_at' => $link->revokedAt?->format('Y-m-d H:i:s'),
            'revoked_at2' => $link->revokedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?MinuteProgramShareLink
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_share_links WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByToken(string $token): ?MinuteProgramShareLink
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_share_links WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByMinuteProgram(string $minuteProgramId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM minute_program_share_links WHERE minute_program_id = :minute_program_id ORDER BY created_at DESC');
        $stmt->execute(['minute_program_id' => $minuteProgramId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): MinuteProgramShareLink
    {
        return new MinuteProgramShareLink(
            id: $row['id'],
            minuteProgramId: $row['minute_program_id'],
            token: $row['token'],
            mode: ShareLinkMode::from($row['mode']),
            createdAt: new DateTimeImmutable($row['created_at']),
            revokedAt: $row['revoked_at'] !== null ? new DateTimeImmutable($row['revoked_at']) : null,
        );
    }
}
