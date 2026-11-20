<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

use DateTimeImmutable;

/**
 * One billed trip of a tour: from a stop (or the start) to the next stop
 * (or the destination); waypoints in between are part of the trip. The
 * kilometres are whole kilometres (rounded per trip).
 */
final class TravelCostTrip
{
    public function __construct(
        public readonly ?DateTimeImmutable $date,
        public readonly string $from,
        public readonly string $to,
        public readonly int $kilometers,
    ) {
    }

    /** @return array{date: ?string, from: string, to: string, km: int} */
    public function toArray(): array
    {
        return ['date' => $this->date?->format('Y-m-d'), 'from' => $this->from, 'to' => $this->to, 'km' => $this->kilometers];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $date = isset($data['date']) && is_string($data['date']) && $data['date'] !== '' ? new DateTimeImmutable($data['date']) : null;
        $from = $data['from'] ?? '';
        $to = $data['to'] ?? '';
        $km = $data['km'] ?? 0;

        return new self($date, is_scalar($from) ? (string) $from : '', is_scalar($to) ? (string) $to : '', is_numeric($km) ? (int) $km : 0);
    }
}
