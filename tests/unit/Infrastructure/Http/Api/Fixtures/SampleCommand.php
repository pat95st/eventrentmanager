<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api\Fixtures;

use App\Domain\Customer\CustomerType;
use DateTimeImmutable;

/** Test fixture for CommandFactoryTest. */
final class SampleCommand
{
    /** @param list<array<string, mixed>> $rows */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $price,
        public readonly bool $active,
        public readonly DateTimeImmutable $startDate,
        public readonly CustomerType $type,
        public readonly array $rows,
        public readonly ?string $note = null,
    ) {
    }
}
