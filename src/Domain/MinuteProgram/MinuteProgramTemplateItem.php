<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

final class MinuteProgramTemplateItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $templateId,
        public int $sortOrder,
        public string $name,
        public ?string $description,
        public ?string $noteGeneral,
        public ?string $noteSound,
        public ?string $noteLight,
        public ?string $noteVideo,
        public ?string $noteStage,
        public ?int $durationMinutes,
    ) {
    }
}
