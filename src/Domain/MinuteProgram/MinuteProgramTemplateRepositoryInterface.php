<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

interface MinuteProgramTemplateRepositoryInterface
{
    public function save(MinuteProgramTemplate $template): void;

    public function findById(string $id): ?MinuteProgramTemplate;

    /** @return MinuteProgramTemplate[] sortiert nach name aufsteigend */
    public function findAll(): array;

    public function delete(string $id): void;
}
