<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;

/** In-Memory-Test-Double für UserRepositoryInterface (siehe tests/README.md). */
final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $user) {
            if ($user->email === $email) {
                return $user;
            }
        }

        return null;
    }

    public function existsByEmail(string $email, ?string $excludingId = null): bool
    {
        $user = $this->findByEmail($email);

        return $user !== null && $user->id !== $excludingId;
    }

    public function findAll(int $limit, int $offset): array
    {
        return array_slice(array_values($this->users), $offset, $limit);
    }

    public function findAllActive(): array
    {
        return array_values(array_filter($this->users, static fn (User $user) => $user->isActive));
    }

    public function countAll(): int
    {
        return count($this->users);
    }

    public function delete(string $id): void
    {
        unset($this->users[$id]);
    }

    public function countActiveAdministrators(?string $excludingId = null): int
    {
        return count(array_filter(
            $this->users,
            static fn (User $user) => $user->isActive && $user->isAdministrator() && $user->id !== $excludingId,
        ));
    }
}
