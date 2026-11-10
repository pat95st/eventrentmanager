<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\AudioPairingCode;
use App\Domain\EventAudio\AudioPairingCodeRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Kopplungs-Codes für Abspielgeräte (Migration 141). */
final class MariadbAudioPairingCodeRepository implements AudioPairingCodeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(AudioPairingCode $code): void
    {
        $this->pdo->prepare(
            'INSERT INTO audio_pairing_codes (id, rental_id, code, created_by_name, created_at, expires_at, used_at, player_id)
             VALUES (:id, :rental, :code, :by, :created, :expires, :used, :player)
             ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), used_at = VALUES(used_at), player_id = VALUES(player_id)'
        )->execute([
            'id' => $code->id,
            'rental' => $code->rentalId,
            'code' => $code->code,
            'by' => $code->createdByName,
            'created' => $code->createdAt->format('Y-m-d H:i:s'),
            'expires' => $code->expiresAt->format('Y-m-d H:i:s'),
            'used' => $code->usedAt?->format('Y-m-d H:i:s'),
            'player' => $code->playerId,
        ]);
    }

    public function findUsable(string $code, DateTimeImmutable $now): ?AudioPairingCode
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM audio_pairing_codes WHERE code = :code AND used_at IS NULL AND expires_at > :now ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['code' => $code, 'now' => $now->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findActiveForRental(string $rentalId, DateTimeImmutable $now): ?AudioPairingCode
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM audio_pairing_codes WHERE rental_id = :rental AND used_at IS NULL AND expires_at > :now ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute(['rental' => $rentalId, 'now' => $now->format('Y-m-d H:i:s')]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function invalidateForRental(string $rentalId, DateTimeImmutable $now): void
    {
        $this->pdo->prepare(
            'UPDATE audio_pairing_codes SET expires_at = :now WHERE rental_id = :rental AND used_at IS NULL AND expires_at > :now2'
        )->execute(['rental' => $rentalId, 'now' => $now->format('Y-m-d H:i:s'), 'now2' => $now->format('Y-m-d H:i:s')]);
    }

    public function recordFailure(string $ip, DateTimeImmutable $at): void
    {
        $this->pdo->prepare('INSERT INTO audio_pairing_failures (ip, attempted_at) VALUES (:ip, :at)')
            ->execute(['ip' => $ip, 'at' => $at->format('Y-m-d H:i:s')]);
        // Alte Einträge nebenbei aufräumen
        $this->pdo->prepare('DELETE FROM audio_pairing_failures WHERE attempted_at < :old')
            ->execute(['old' => $at->modify('-1 day')->format('Y-m-d H:i:s')]);
    }

    public function countFailuresSince(string $ip, DateTimeImmutable $since): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM audio_pairing_failures WHERE ip = :ip AND attempted_at >= :since');
        $stmt->execute(['ip' => $ip, 'since' => $since->format('Y-m-d H:i:s')]);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): AudioPairingCode
    {
        return new AudioPairingCode(
            (string) $row['id'],
            (string) $row['rental_id'],
            (string) $row['code'],
            (string) $row['created_by_name'],
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['expires_at']),
            $row['used_at'] !== null ? new DateTimeImmutable((string) $row['used_at']) : null,
            $row['player_id'] !== null ? (string) $row['player_id'] : null,
        );
    }
}
