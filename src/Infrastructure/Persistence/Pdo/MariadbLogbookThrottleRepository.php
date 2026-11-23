<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Logbook\LogbookThrottleRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbLogbookThrottleRepository implements LogbookThrottleRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT counter, window_start, locked_until FROM logbook_attempts WHERE attempt_key = :k');
        $stmt->execute(['k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'counter' => (int) $row['counter'],
            'windowStart' => new DateTimeImmutable($row['window_start']),
            'lockedUntil' => $row['locked_until'] !== null ? new DateTimeImmutable($row['locked_until']) : null,
        ];
    }

    public function store(string $key, string $scope, int $counter, DateTimeImmutable $windowStart, ?DateTimeImmutable $lockedUntil): void
    {
        $this->pdo->prepare(
            'INSERT INTO logbook_attempts (attempt_key, scope, counter, window_start, locked_until) VALUES (:k, :scope, :counter, :start, :locked)
             ON DUPLICATE KEY UPDATE counter = VALUES(counter), window_start = VALUES(window_start), locked_until = VALUES(locked_until)'
        )->execute([
            'k' => $key,
            'scope' => $scope,
            'counter' => $counter,
            'start' => $windowStart->format('Y-m-d H:i:s'),
            'locked' => $lockedUntil?->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $key): void
    {
        $this->pdo->prepare('DELETE FROM logbook_attempts WHERE attempt_key = :k')->execute(['k' => $key]);
    }

    public function purge(DateTimeImmutable $before): void
    {
        $this->pdo->prepare('DELETE FROM logbook_attempts WHERE updated_at < :before AND (locked_until IS NULL OR locked_until < :before2)')
            ->execute(['before' => $before->format('Y-m-d H:i:s'), 'before2' => $before->format('Y-m-d H:i:s')]);
    }
}
