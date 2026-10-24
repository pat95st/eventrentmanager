<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface LoginChallengeRepositoryInterface
{
    public function save(LoginChallenge $challenge): void;

    /** Neueste, noch nicht verbrauchte Challenge eines Benutzers (für die erneute Prüfung beim Absenden). */
    public function findLatestActiveForUser(string $userId): ?LoginChallenge;

    public function findById(string $id): ?LoginChallenge;
}
