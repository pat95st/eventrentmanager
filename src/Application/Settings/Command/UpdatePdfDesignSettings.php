<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdatePdfDesignSettings implements CommandInterface
{
    public function __construct(
        public readonly string $accentColorHex, // "#rrggbb" aus dem <input type="color">, wird im Handler geparst
        public readonly string $logoPosition, // 'left' | 'right'
    ) {
    }
}
