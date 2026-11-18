<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api\Fixtures;

/** Test fixture for CommandFactoryTest. */
final class StringCommand
{
    public function __construct(
        public readonly string $type,
        public readonly string $startDate,
        public readonly string $pickupAt,
        public readonly string $amount,
        public readonly bool $flag,
    ) {
    }
}
