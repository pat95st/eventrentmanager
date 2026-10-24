<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface WebauthnCredentialRepositoryInterface
{
    public function save(WebauthnCredential $credential): void;

    public function findById(string $id): ?WebauthnCredential;

    public function findByCredentialId(string $credentialId): ?WebauthnCredential;

    /** @return WebauthnCredential[] */
    public function findAllForUser(string $userId): array;

    public function existsCredentialId(string $credentialId): bool;

    public function delete(string $id): void;
}
