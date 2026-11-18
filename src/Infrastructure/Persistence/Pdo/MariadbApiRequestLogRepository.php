<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Api\ApiRequestLogEntry;
use App\Domain\Api\ApiRequestLogRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbApiRequestLogRepository implements ApiRequestLogRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function add(ApiRequestLogEntry $entry): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_request_log (token_id, token_name, method, path, status_code, ip, created_at)
             VALUES (:token_id, :token_name, :method, :path, :status_code, :ip, :created_at)'
        );
        $stmt->execute([
            'token_id' => $entry->tokenId,
            'token_name' => mb_substr($entry->tokenName, 0, 255),
            'method' => $entry->method,
            'path' => mb_substr($entry->path, 0, 500),
            'status_code' => $entry->statusCode,
            'ip' => $entry->ip,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function latest(int $limit, ?string $tokenId = null): array
    {
        $sql = 'SELECT * FROM api_request_log' . ($tokenId !== null ? ' WHERE token_id = :token_id' : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($tokenId !== null ? ['token_id' => $tokenId] : []);

        return array_map(static fn (array $row) => new ApiRequestLogEntry(
            tokenId: $row['token_id'],
            tokenName: $row['token_name'],
            method: $row['method'],
            path: $row['path'],
            statusCode: (int) $row['status_code'],
            ip: $row['ip'],
            createdAt: new DateTimeImmutable($row['created_at']),
        ), $stmt->fetchAll());
    }

    public function purgeOlderThan(DateTimeImmutable $before): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_request_log WHERE created_at < :before');
        $stmt->execute(['before' => $before->format('Y-m-d H:i:s')]);
    }
}
