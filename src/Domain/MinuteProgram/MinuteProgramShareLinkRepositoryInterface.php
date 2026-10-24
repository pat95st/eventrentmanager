<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

interface MinuteProgramShareLinkRepositoryInterface
{
    public function save(MinuteProgramShareLink $link): void;

    public function findById(string $id): ?MinuteProgramShareLink;

    public function findByToken(string $token): ?MinuteProgramShareLink;

    /** @return MinuteProgramShareLink[] sortiert nach created_at absteigend */
    public function findByMinuteProgram(string $minuteProgramId): array;
}
