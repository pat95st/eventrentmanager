<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

interface MinuteProgramTemplateItemRepositoryInterface
{
    public function save(MinuteProgramTemplateItem $item): void;

    public function findById(string $id): ?MinuteProgramTemplateItem;

    /** @return MinuteProgramTemplateItem[] sortiert nach sort_order aufsteigend */
    public function findByTemplate(string $templateId): array;

    public function nextSortOrder(string $templateId): int;

    public function delete(string $id): void;
}
