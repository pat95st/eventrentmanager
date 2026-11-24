<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Api\ApiScope;
use App\Domain\OAuth\OAuthAuthorizationCode;
use App\Domain\OAuth\OAuthAuthorizationCodeRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbOAuthAuthorizationCodeRepository implements OAuthAuthorizationCodeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(OAuthAuthorizationCode $code): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_authorization_codes (code_hash, client_id, user_id, redirect_uri, code_challenge, scopes, resource, expires_at, created_at)
             VALUES (:code_hash, :client_id, :user_id, :redirect_uri, :code_challenge, :scopes, :resource, :expires_at, :created_at)'
        );
        $stmt->execute([
            'code_hash' => $code->codeHash,
            'client_id' => $code->clientId,
            'user_id' => $code->userId,
            'redirect_uri' => $code->redirectUri,
            'code_challenge' => $code->codeChallenge,
            'scopes' => json_encode(array_map(static fn (ApiScope $s) => $s->value, $code->scopes), JSON_THROW_ON_ERROR),
            'resource' => $code->resource,
            'expires_at' => $code->expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $code->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function consume(string $codeHash): ?OAuthAuthorizationCode
    {
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_authorization_codes WHERE code_hash = :hash');
        $stmt->execute(['hash' => $codeHash]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        // Single use: only the request that actually deletes the row may use the code.
        $delete = $this->pdo->prepare('DELETE FROM oauth_authorization_codes WHERE code_hash = :hash');
        $delete->execute(['hash' => $codeHash]);
        if ($delete->rowCount() !== 1) {
            return null;
        }

        return new OAuthAuthorizationCode(
            codeHash: $row['code_hash'],
            clientId: $row['client_id'],
            userId: $row['user_id'],
            redirectUri: $row['redirect_uri'],
            codeChallenge: $row['code_challenge'],
            scopes: ApiScope::parseList((array) (json_decode((string) $row['scopes'], true) ?? [])),
            resource: $row['resource'] ?? null,
            expiresAt: new DateTimeImmutable($row['expires_at']),
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }

    public function purgeExpired(DateTimeImmutable $now): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM oauth_authorization_codes WHERE expires_at < :now');
        $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);
    }
}
