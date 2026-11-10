<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\OrganizerLogin;
use App\Domain\EventAudio\OrganizerLoginRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbOrganizerLoginRepository implements OrganizerLoginRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_organizer_logins WHERE rental_id = :id ORDER BY created_at');
        $stmt->execute(['id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?OrganizerLogin
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_organizer_logins WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?OrganizerLogin
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_organizer_logins WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(OrganizerLogin $login): void
    {
        $fmt = static fn (?DateTimeImmutable $d): ?string => $d?->format('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO rental_organizer_logins (id, rental_id, label, username, password_hash, valid_from, valid_until, enabled, failed_attempts, locked_until, last_login_at, created_at, can_schedule)
             VALUES (:id, :rental, :label, :username, :hash, :from, :until, :enabled, :failed, :locked, :last, :created, :schedule)
             ON DUPLICATE KEY UPDATE label = VALUES(label), username = VALUES(username), password_hash = VALUES(password_hash),
                valid_from = VALUES(valid_from), valid_until = VALUES(valid_until), enabled = VALUES(enabled),
                failed_attempts = VALUES(failed_attempts), locked_until = VALUES(locked_until), last_login_at = VALUES(last_login_at), can_schedule = VALUES(can_schedule)'
        )->execute([
            'id' => $login->id,
            'rental' => $login->rentalId,
            'label' => $login->label,
            'username' => $login->username,
            'hash' => $login->passwordHash,
            'from' => $fmt($login->validFrom),
            'until' => $fmt($login->validUntil),
            'enabled' => $login->enabled ? 1 : 0,
            'failed' => $login->failedAttempts,
            'locked' => $fmt($login->lockedUntil),
            'last' => $fmt($login->lastLoginAt),
            'created' => $login->createdAt->format('Y-m-d H:i:s'),
            'schedule' => $login->canSchedule ? 1 : 0,
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_organizer_logins WHERE id = :id')->execute(['id' => $id]);
    }

    private function hydrate(array $row): OrganizerLogin
    {
        $d = static fn ($v): ?DateTimeImmutable => $v !== null ? new DateTimeImmutable($v) : null;

        return new OrganizerLogin(
            id: $row['id'],
            rentalId: $row['rental_id'],
            label: $row['label'],
            username: $row['username'],
            passwordHash: $row['password_hash'],
            validFrom: $d($row['valid_from']),
            validUntil: $d($row['valid_until']),
            enabled: (bool) $row['enabled'],
            failedAttempts: (int) $row['failed_attempts'],
            lockedUntil: $d($row['locked_until']),
            lastLoginAt: $d($row['last_login_at']),
            createdAt: new DateTimeImmutable($row['created_at']),
            canSchedule: (bool) ($row['can_schedule'] ?? false),
        );
    }
}
