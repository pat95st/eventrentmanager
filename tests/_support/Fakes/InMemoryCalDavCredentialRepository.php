<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Calendar\CalDavCredential;
use App\Domain\Calendar\CalDavCredentialRepositoryInterface;
use DateTimeImmutable;

/** In-Memory-Test-Double für CalDavCredentialRepositoryInterface, siehe InMemoryCalendarRepository. */
final class InMemoryCalDavCredentialRepository implements CalDavCredentialRepositoryInterface
{
    /** @var array<string, CalDavCredential> */
    private array $credentials = [];

    public function save(CalDavCredential $credential): void
    {
        $this->credentials[$credential->id] = $credential;
    }

    public function findById(string $id): ?CalDavCredential
    {
        return $this->credentials[$id] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?CalDavCredential
    {
        foreach ($this->credentials as $credential) {
            if ($credential->tokenHash === $tokenHash) {
                return $credential;
            }
        }

        return null;
    }

    public function findByUser(string $userId): array
    {
        $credentials = array_values(array_filter(
            $this->credentials,
            static fn (CalDavCredential $credential): bool => $credential->userId === $userId
        ));

        usort($credentials, static fn (CalDavCredential $a, CalDavCredential $b): int => $b->createdAt <=> $a->createdAt);

        return $credentials;
    }

    public function touchLastUsed(string $id, DateTimeImmutable $at): void
    {
        if (isset($this->credentials[$id])) {
            $this->credentials[$id]->lastUsedAt = $at;
        }
    }

    public function delete(string $id): void
    {
        unset($this->credentials[$id]);
    }
}
