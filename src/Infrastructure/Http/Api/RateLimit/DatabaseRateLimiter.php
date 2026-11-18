<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Fixed-window counter in the table api_rate_limits (migration 175). A single
 * atomic upsert increments the counter; LAST_INSERT_ID(expr) hands the new
 * value back without a second query racing other requests.
 */
final class DatabaseRateLimiter implements RateLimiterInterface
{
    /** Probability (1/n) of purging old windows on a hit. */
    private const PURGE_ONE_IN = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function hit(string $tokenId, int $limit, DateTimeImmutable $now): RateLimitResult
    {
        [$start, $reset] = RateLimitResult::window($now);
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_rate_limits (token_id, window_start, hits) VALUES (:token_id, :window_start, 1)
                 ON DUPLICATE KEY UPDATE hits = LAST_INSERT_ID(hits + 1)'
            );
            $stmt->execute(['token_id' => $tokenId, 'window_start' => $start->format('Y-m-d H:i:s')]);
            // rowCount() is 1 for a fresh row (LAST_INSERT_ID not set by us), 2 for an update.
            $hits = $stmt->rowCount() === 1 ? 1 : (int) $this->pdo->lastInsertId();

            if (random_int(1, self::PURGE_ONE_IN) === 1) {
                $purge = $this->pdo->prepare('DELETE FROM api_rate_limits WHERE window_start < :before');
                $purge->execute(['before' => $start->modify('-10 minutes')->format('Y-m-d H:i:s')]);
            }
        } catch (PDOException $e) {
            throw new RateLimiterUnavailable('Datenbank-Zähler nicht verfügbar: ' . $e->getMessage(), 0, $e);
        }

        return new RateLimitResult($limit, max(1, $hits), $reset);
    }
}
