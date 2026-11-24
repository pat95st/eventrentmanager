<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;

interface OAuthClientRepositoryInterface
{
    public function findById(string $id): ?OAuthClient;

    public function save(OAuthClient $client): void;

    public function touchLastUsed(string $id, DateTimeImmutable $at): void;

    /** Removes clients registered before $before that never got a grant (open registration leaves such rows behind). */
    public function purgeUnusedOlderThan(DateTimeImmutable $before): void;
}
