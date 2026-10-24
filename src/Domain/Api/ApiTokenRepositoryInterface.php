<?php

declare(strict_types=1);

namespace App\Domain\Api;

use DateTimeImmutable;

interface ApiTokenRepositoryInterface
{
    /** @return ApiToken[] alphabetisch nach Name sortiert */
    public function findAll(): array;

    public function findById(string $id): ?ApiToken;

    public function findByTokenHash(string $tokenHash): ?ApiToken;

    public function save(ApiToken $token): void;

    public function delete(string $id): void;

    /** Leichtgewichtiges Update von last_used_at bei jeder erfolgreichen API-Anfrage, siehe ApiTokenGuard. */
    public function touchLastUsed(string $id, DateTimeImmutable $at): void;
}
