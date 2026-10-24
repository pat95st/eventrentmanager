<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

enum ShareLinkMode: string
{
    case Read = 'read';
    case ReadWrite = 'read_write';

    public function canEdit(): bool
    {
        return $this === self::ReadWrite;
    }

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Nur lesen',
            self::ReadWrite => 'Lesen & bearbeiten',
        };
    }
}
