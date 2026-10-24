<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class AddMinuteProgramTemplateItem implements CommandInterface
{
    public function __construct(
        public readonly string $templateId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $noteGeneral,
        public readonly ?string $noteSound,
        public readonly ?string $noteLight,
        public readonly ?string $noteVideo,
        public readonly ?string $noteStage,
        public readonly ?int $durationMinutes,
    ) {
    }
}
