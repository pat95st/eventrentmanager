<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayClientRepositoryInterface;
use App\Domain\Display\DisplayOptions;
use App\Domain\Display\DisplayRole;
use DateTimeImmutable;
use PDO;

/** Stage and FOH displays (migration 168). */
final class MariadbDisplayClientRepository implements DisplayClientRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->pdo->query("SELECT * FROM display_clients ORDER BY FIELD(role, 'stage', 'foh'), name")->fetchAll());
    }

    public function findById(string $id): ?DisplayClient
    {
        $stmt = $this->pdo->prepare('SELECT * FROM display_clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $hash): ?DisplayClient
    {
        $stmt = $this->pdo->prepare('SELECT * FROM display_clients WHERE token_hash = :hash');
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(DisplayClient $client): void
    {
        $this->pdo->prepare(
            'INSERT INTO display_clients (id, name, role, token_hash, token_hint, enabled, rental_id, minute_program_id, room, options_json, created_at)
             VALUES (:id, :name, :role, :hash, :hint, :enabled, :rental, :program, :room, :options, :created)
             ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), token_hash = VALUES(token_hash), token_hint = VALUES(token_hint),
                enabled = VALUES(enabled), rental_id = VALUES(rental_id), minute_program_id = VALUES(minute_program_id), room = VALUES(room),
                options_json = VALUES(options_json)'
        )->execute([
            'id' => $client->id,
            'name' => $client->name,
            'role' => $client->role->value,
            'hash' => $client->tokenHash,
            'hint' => $client->tokenHint,
            'enabled' => $client->enabled ? 1 : 0,
            'rental' => $client->rentalId,
            'program' => $client->minuteProgramId,
            'room' => $client->room,
            'options' => json_encode($client->options->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created' => $client->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM display_clients WHERE id = :id')->execute(['id' => $id]);
    }

    public function touch(string $id, DateTimeImmutable $at, ?string $ip, ?string $version): void
    {
        $this->pdo->prepare(
            'UPDATE display_clients SET last_seen_at = :at, last_ip = :ip, client_version = COALESCE(:version, client_version) WHERE id = :id'
        )->execute([
            'id' => $id,
            'at' => $at->format('Y-m-d H:i:s'),
            'ip' => $ip !== null ? substr($ip, 0, 45) : null,
            'version' => $version !== null ? mb_substr($version, 0, 60) : null,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): DisplayClient
    {
        $role = DisplayRole::fromInput($row['role']);
        $options = $row['options_json'] !== null ? json_decode((string) $row['options_json'], true) : null;

        return new DisplayClient(
            id: $row['id'],
            name: $row['name'],
            role: $role,
            tokenHash: $row['token_hash'],
            tokenHint: $row['token_hint'],
            enabled: (bool) $row['enabled'],
            rentalId: $row['rental_id'],
            minuteProgramId: $row['minute_program_id'],
            room: $row['room'],
            options: DisplayOptions::fromArray(is_array($options) ? $options : null, $role),
            lastSeenAt: $row['last_seen_at'] !== null ? new DateTimeImmutable($row['last_seen_at']) : null,
            lastIp: $row['last_ip'],
            clientVersion: $row['client_version'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
