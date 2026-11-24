<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\OAuth\OAuthClient;
use App\Domain\OAuth\OAuthClientRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbOAuthClientRepository implements OAuthClientRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $id): ?OAuthClient
    {
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_clients WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(OAuthClient $client): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_clients (id, name, redirect_uris, client_secret_hash, token_endpoint_auth_method, created_at, last_used_at)
             VALUES (:id, :name, :redirect_uris, :client_secret_hash, :token_endpoint_auth_method, :created_at, :last_used_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), redirect_uris = VALUES(redirect_uris), last_used_at = VALUES(last_used_at)'
        );
        $stmt->execute([
            'id' => $client->id,
            'name' => $client->name,
            'redirect_uris' => json_encode($client->redirectUris, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'client_secret_hash' => $client->clientSecretHash,
            'token_endpoint_auth_method' => $client->tokenEndpointAuthMethod,
            'created_at' => $client->createdAt->format('Y-m-d H:i:s'),
            'last_used_at' => $client->lastUsedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function touchLastUsed(string $id, DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare('UPDATE oauth_clients SET last_used_at = :at WHERE id = :id');
        $stmt->execute(['id' => $id, 'at' => $at->format('Y-m-d H:i:s')]);
    }

    public function purgeUnusedOlderThan(DateTimeImmutable $before): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM oauth_clients
             WHERE created_at < :before
               AND NOT EXISTS (SELECT 1 FROM oauth_grants g WHERE g.client_id = oauth_clients.id)
               AND NOT EXISTS (SELECT 1 FROM oauth_authorization_codes c WHERE c.client_id = oauth_clients.id)'
        );
        $stmt->execute(['before' => $before->format('Y-m-d H:i:s')]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): OAuthClient
    {
        $uris = json_decode((string) $row['redirect_uris'], true);

        return new OAuthClient(
            id: $row['id'],
            name: $row['name'],
            redirectUris: is_array($uris) ? array_values(array_map('strval', $uris)) : [],
            clientSecretHash: $row['client_secret_hash'] ?? null,
            tokenEndpointAuthMethod: (string) $row['token_endpoint_auth_method'],
            createdAt: new DateTimeImmutable($row['created_at']),
            lastUsedAt: isset($row['last_used_at']) ? new DateTimeImmutable($row['last_used_at']) : null,
        );
    }
}
