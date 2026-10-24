<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface PasswordSetupTokenRepositoryInterface
{
    public function save(PasswordSetupToken $token): void;

    public function findByTokenHash(string $tokenHash): ?PasswordSetupToken;

    public function markUsed(string $id): void;

    /**
     * Markiert alle noch nicht verwendeten Tokens eines Benutzers als
     * verbraucht — wird beim Ausstellen eines neuen Tokens aufgerufen, damit
     * niemals mehrere gültige Links gleichzeitig im Umlauf sind (siehe
     * App\Application\Auth\PasswordSetupTokenIssuer).
     */
    public function invalidateAllForUser(string $userId): void;
}
