<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/**
 * Compatibility of a digital stagebox with a mixing console (migration 153).
 * "Limited" covers stageboxes that speak the same protocol but are not
 * natively supported, e.g. because they are made by another manufacturer.
 */
enum StageboxCompatibility: string
{
    case Native = 'native';
    case Limited = 'limited';

    public function label(): string
    {
        return match ($this) {
            self::Native => 'nativ kompatibel',
            self::Limited => 'kompatibel mit Einschränkungen',
        };
    }
}
