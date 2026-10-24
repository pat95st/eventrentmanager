<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface WebauthnChallengeRepositoryInterface
{
    public function save(WebauthnChallenge $challenge): void;

    public function findById(string $id): ?WebauthnChallenge;

    public function markConsumed(string $id): void;
}
