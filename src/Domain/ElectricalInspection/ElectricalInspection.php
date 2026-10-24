<?php

declare(strict_types=1);

namespace App\Domain\ElectricalInspection;

use DateInterval;
use DateTimeImmutable;

final class ElectricalInspection
{
    public function __construct(
        public readonly string $id,
        public readonly string $inventoryNumberId,
        public DateTimeImmutable $inspectedAt,
        public int $validityYears,   // 1 oder 2
        public bool $passed,
        public ?string $inspector,
        public ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function validUntil(): DateTimeImmutable
    {
        return $this->inspectedAt->add(new DateInterval('P' . $this->validityYears . 'Y'));
    }

    public function status(DateTimeImmutable $today = new DateTimeImmutable('today')): ElectricalInspectionStatus
    {
        return ElectricalInspectionStatus::compute($this->passed, $this->validUntil(), $today);
    }
}
