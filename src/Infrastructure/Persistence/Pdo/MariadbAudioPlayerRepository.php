<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\AudioPlayer;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Abspielgeräte (Migration 135). */
final class MariadbAudioPlayerRepository implements AudioPlayerRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->pdo->query('SELECT * FROM audio_players ORDER BY name')->fetchAll());
    }

    public function findById(string $id): ?AudioPlayer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audio_players WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $hash): ?AudioPlayer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audio_players WHERE token_hash = :hash');
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(AudioPlayer $player): void
    {
        $this->pdo->prepare(
            'INSERT INTO audio_players (id, name, token_hash, token_hint, enabled, created_at)
             VALUES (:id, :name, :hash, :hint, :enabled, :created)
             ON DUPLICATE KEY UPDATE name = VALUES(name), token_hash = VALUES(token_hash), token_hint = VALUES(token_hint), enabled = VALUES(enabled)'
        )->execute([
            'id' => $player->id,
            'name' => $player->name,
            'hash' => $player->tokenHash,
            'hint' => $player->tokenHint,
            'enabled' => $player->enabled ? 1 : 0,
            'created' => $player->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM audio_players WHERE id = :id')->execute(['id' => $id]);
    }

    public function touch(string $id, DateTimeImmutable $at, ?string $ip, ?string $version, ?string $nowPlaying): void
    {
        $this->pdo->prepare(
            'UPDATE audio_players SET last_seen_at = :at, last_ip = :ip, player_version = COALESCE(:version, player_version), now_playing = :playing WHERE id = :id'
        )->execute([
            'id' => $id,
            'at' => $at->format('Y-m-d H:i:s'),
            'ip' => $ip !== null ? substr($ip, 0, 45) : null,
            'version' => $version !== null ? substr($version, 0, 60) : null,
            'playing' => $nowPlaying !== null && $nowPlaying !== '' ? mb_substr($nowPlaying, 0, 255) : null,
        ]);
    }

    private function hydrate(array $row): AudioPlayer
    {
        return new AudioPlayer(
            id: $row['id'],
            name: $row['name'],
            tokenHash: $row['token_hash'],
            tokenHint: $row['token_hint'],
            enabled: (bool) $row['enabled'],
            lastSeenAt: $row['last_seen_at'] !== null ? new DateTimeImmutable($row['last_seen_at']) : null,
            lastIp: $row['last_ip'],
            playerVersion: $row['player_version'],
            nowPlaying: $row['now_playing'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
