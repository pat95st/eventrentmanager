<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface ImpersonationRepositoryInterface
{
    public function save(Impersonation $impersonation): void;

    public function findById(string $id): ?Impersonation;

    /** @return Impersonation[] Nach started_at absteigend (neueste zuerst), höchstens $limit Zeilen ab $offset. */
    public function findAll(?int $limit = null, ?int $offset = null): array;

    public function countAll(): int;
}
