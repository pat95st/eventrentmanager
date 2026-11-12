<?php

declare(strict_types=1);

namespace App\Application\Insurance\Estimation;

final class CurrentValueEstimate
{
    public function __construct(
        public readonly string $value, // "1234.50"
        public readonly ?string $reasoning,
    ) {
    }
}
